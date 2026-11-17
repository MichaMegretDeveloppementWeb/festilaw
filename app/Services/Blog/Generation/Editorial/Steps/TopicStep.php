<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\TopicAgent;
use App\Data\Blog\Generation\GenerationFindings;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Exceptions\Blog\BlogEditorialException;
use App\Repositories\Blog\BlogTopicHistoryRepository;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\BlogTopicSimilarity;
use Carbon\CarbonImmutable;

/**
 * Etape 1 · le sujet. L'agent choisit un sujet d'apres les reglages, la demande et la liste complete des
 * titres connus, ou repond qu'aucun n'est assez pertinent : la generation s'arrete alors sans article. Un
 * titre fait des memes mots qu'un titre connu est ecarte en code (doublon), meme si l'agent ne l'a pas vu.
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
        $agent = new TopicAgent($this->prompts->compose('festilaw-context', 'editorial-guidelines', 'step-topic'));
        $reply = $this->ask($context, $agent, implode("\n\n", [
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Request', [
                'imposed_topic' => $context->topicHint,
                'instructions' => $context->instructions,
            ]),
            $this->block('Priority topics', $context->settings->priorityTopics),
            $this->block('Topics to avoid', $context->settings->avoidedTopics),
            $this->block('Existing articles', array_map(static fn (array $post): array => ['title' => $post['title'], 'excerpt' => $post['excerpt'], 'status' => $post['status']], $history->posts)),
            $this->block('Topics already proposed', $history->proposedTopics),
        ]));

        $answer = $reply->structured();
        $decision = $this->text($answer, 'decision');

        if (! in_array($decision, ['topic', 'none'], true)) {
            throw BlogEditorialException::unusableAnswer($this->key(), 'missing or unknown decision');
        }

        if ($decision === 'none') {
            return GenerationStepResult::stop(
                BlogGenerationOutcomeReason::NoRelevantTopic,
                ['decision' => 'none', 'reason' => $this->text($answer, 'reason')],
                $reply->usage,
                $this->report($reply, __('Aucun sujet assez pertinent n\'a été trouvé, donc aucun article n\'a été écrit.')),
            );
        }

        $title = $this->text($answer, 'title');
        $kind = BlogTopicKind::tryFrom($this->text($answer, 'kind'));

        if ($title === '' || $kind === null) {
            throw BlogEditorialException::unusableAnswer($this->key(), 'a topic without a title or a kind');
        }

        $output = [
            'decision' => 'topic',
            'kind' => $kind->value,
            'title' => $title,
            'angle' => $this->text($answer, 'angle'),
            'reader' => $this->text($answer, 'reader'),
            'keywords' => array_values(array_filter($this->items($answer, 'keywords'), is_string(...))),
            'reason' => $this->text($answer, 'reason'),
            'citations' => $reply->citations(),
        ];
        $duplicateOf = $this->similarity->duplicateOf($title, $history->titles());

        if ($duplicateOf !== null) {
            return GenerationStepResult::stop(
                BlogGenerationOutcomeReason::DuplicateTopic,
                [...$output, 'duplicate_of' => $duplicateOf],
                $reply->usage,
                __('Le sujet proposé reprenait « :title », déjà traité ou refusé, donc aucun article n\'a été écrit.', ['title' => $duplicateOf]),
                new GenerationFindings(topicTitle: $title, topicKind: $kind),
            );
        }

        return GenerationStepResult::continue(
            $output,
            $reply->usage,
            $this->report($reply, __('Le sujet retenu est « :title ».', ['title' => $title])),
            new GenerationFindings(topicTitle: $title, topicSummary: $output['angle'] ?: null, topicKind: $kind),
        );
    }
}
