<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\TopicAgent;
use App\Data\Blog\Generation\GenerationFindings;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Repositories\Blog\BlogTopicHistoryRepository;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\BlogTopicSimilarity;
use Carbon\CarbonImmutable;

/**
 * Choisit le sujet d'apres les notes de la veille, ou repond qu'aucun n'est assez pertinent : la generation
 * s'arrete alors sans article. L'agent ne cherche pas lui-meme, la veille est une etape a part. Un titre fait
 * des memes mots qu'un titre connu est ecarte en code, car l'agent peut ne pas le voir. Un sujet impose ne
 * l'est jamais : la personne qui l'impose le veut, et l'agent en choisit un angle que les articles existants
 * ne prennent pas (decision de Micha du 18/11, apres un sujet impose refuse par le banc d'essai).
 */
final class TopicStep extends EditorialStep
{
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
        $scan = $context->output('topic_scan');
        $notes = trim((string) ($scan['notes'] ?? ''));
        $citations = is_array($scan['citations'] ?? null) ? $scan['citations'] : [];

        $agent = new TopicAgent($this->prompts->compose('festilaw-context', 'editorial-guidelines', 'step-topic'));
        $reply = $this->ask($context, $agent, implode("\n\n", array_filter([
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Request', [
                'imposed_topic' => $context->topicHint,
                'instructions' => $context->instructions,
            ]),
            $this->block('Length of the article', 'About '.($context->length->words ?? $context->settings->articleWords).' words in the body.'),
            $this->block('Existing articles', array_map(static fn (array $post): array => ['title' => $post['title'], 'excerpt' => $post['excerpt'], 'status' => $post['status']], $history->posts)),
            $history->olderTitles === [] ? null : $this->block('Older articles (titles only)', $history->olderTitles),
            $this->block('Topics already proposed', $history->proposedTopics),
            $this->block('Notes from the web scan', match (true) {
                filled($context->topicHint) => 'No web scan: the topic is imposed, and the research step will search the web.',
                $notes === '' => 'No notes.',
                default => $notes,
            }),
            $this->block('Pages cited by the search', $citations),
        ])));
        $usage = $reply->usage;

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
            'citations' => $citations,
        ];
        $duplicateOf = filled($context->topicHint) ? null : $this->similarity->duplicateOf($title, $history->titles(), $kind);

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
