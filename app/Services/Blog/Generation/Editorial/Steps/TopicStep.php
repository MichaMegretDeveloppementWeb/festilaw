<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\GenerationFindings;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Repositories\Blog\BlogTopicHistoryRepository;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\BlogTopicSimilarity;
use Carbon\CarbonImmutable;

/**
 * Etape 1 · le sujet, en deux temps. Un agent fait la veille sur le web (actualite reglementaire, questions
 * des vendeurs) et rend ses notes en texte libre ; un second agent, sans outil, choisit le sujet d'apres ces
 * notes, les reglages, la demande et la liste complete des titres connus, ou repond qu'aucun n'est assez
 * pertinent : la generation s'arrete alors sans article. Deux temps, car un modele qui cherche sur le web
 * ignore le format JSON demande (essai reel du 17/11). Un titre fait des memes mots qu'un titre connu est
 * ecarte en code (doublon), meme si l'agent ne l'a pas vu.
 *
 * Un sujet impose se passe de veille : la recherche de l'article cherchera de toute facon sur le web.
 */
final class TopicStep extends EditorialStep
{
    /** Recherches web de la veille (0,01 $ chacune, plus les pages qu'elles chargent) : decision du 17/11. */
    private const WEB_SEARCHES = 2;

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogTopicHistoryRepository $history,
        private readonly BlogTopicSimilarity $similarity,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'topic';
    }

    public function timeoutSeconds(): int
    {
        return 300;
    }

    protected function turnTimeoutSeconds(): int
    {
        return 120;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $history = $this->history->load(exceptGenerationId: $context->generationId);
        $request = [
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Request', [
                'imposed_topic' => $context->topicHint,
                'instructions' => $context->instructions,
            ]),
            $this->block('Priority topics', $context->settings->priorityTopics),
            $this->block('Topics to avoid', $context->settings->avoidedTopics),
        ];

        $scan = filled($context->topicHint)
            ? null
            : $this->ask($context, new WebSearchAgent($this->prompts->compose('festilaw-context', 'step-topic-scan'), searches: self::WEB_SEARCHES), implode("\n\n", $request));
        $spent = $scan->usage ?? GenerationUsage::none();
        $agent = new TopicAgent($this->prompts->compose('festilaw-context', 'editorial-guidelines', 'step-topic'));
        $reply = $this->ask($context, $agent, spent: $spent, prompt: implode("\n\n", [
            ...$request,
            $this->block('Existing articles', array_map(static fn (array $post): array => ['title' => $post['title'], 'excerpt' => $post['excerpt'], 'status' => $post['status']], $history->posts)),
            $this->block('Topics already proposed', $history->proposedTopics),
            $this->block('Notes from the web scan', match (true) {
                $scan === null => 'No web scan: the topic is imposed, and the research step will search the web.',
                $scan->text() === '' => 'No notes.',
                default => $scan->text(),
            }),
            $this->block('Pages cited by the search', $scan?->citations() ?? []),
        ]));
        $usage = $spent->plus($reply->usage);

        $answer = $reply->structured();
        $decision = $this->text($answer, 'decision');

        if (! in_array($decision, ['topic', 'none'], true)) {
            $this->unusable('missing or unknown decision', $usage);
        }

        if ($decision === 'none') {
            return GenerationStepResult::stop(
                BlogGenerationOutcomeReason::NoRelevantTopic,
                ['decision' => 'none', 'reason' => $this->text($answer, 'reason')],
                $usage,
                $this->report($reply, __('Aucun sujet assez pertinent n\'a été trouvé, donc aucun article n\'a été écrit.')),
            );
        }

        $title = $this->text($answer, 'title');
        $kind = BlogTopicKind::tryFrom($this->text($answer, 'kind'));

        if ($title === '' || $kind === null) {
            $this->unusable('a topic without a title or a kind', $usage);
        }

        $output = [
            'decision' => 'topic',
            'kind' => $kind->value,
            'title' => $title,
            'angle' => $this->text($answer, 'angle'),
            'reader' => $this->text($answer, 'reader'),
            'keywords' => array_values(array_filter($this->items($answer, 'keywords'), is_string(...))),
            'reason' => $this->text($answer, 'reason'),
            'citations' => $scan?->citations() ?? [],
        ];
        $duplicateOf = $this->similarity->duplicateOf($title, $history->titles());

        if ($duplicateOf !== null) {
            return GenerationStepResult::stop(
                BlogGenerationOutcomeReason::DuplicateTopic,
                [...$output, 'duplicate_of' => $duplicateOf],
                $usage,
                __('Le sujet proposé reprenait « :title », déjà traité ou refusé, donc aucun article n\'a été écrit.', ['title' => $duplicateOf]),
                new GenerationFindings(topicTitle: $title, topicKind: $kind),
            );
        }

        return GenerationStepResult::continue(
            $output,
            $usage,
            $this->report($reply, __('Le sujet retenu est « :title ».', ['title' => $title])),
            new GenerationFindings(topicTitle: $title, topicSummary: $output['angle'] ?: null, topicKind: $kind),
        );
    }
}
