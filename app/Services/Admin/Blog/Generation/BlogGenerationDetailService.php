<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Admin\Blog\Generation\BlogGenerationDetailView;
use App\Data\Admin\Blog\Generation\BlogGenerationStepView;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogGenerationLabels;

/**
 * Une generation et ses etapes, pour la carte de suivi en direct et pour l'ecran de detail.
 *
 * Plan de requetes (verifie par un test de budget) : 2 (la generation, ses etapes) ; 1 quand il n'y en a
 * aucune. Les etapes pas encore commencees viennent de la liste du pipeline, sans requete.
 *
 * Seules les colonnes affichees sont lues : ni ce qu'une etape a produit (le texte d'un article, relu toutes
 * les deux secondes par la carte de suivi), ni la cause technique d'un echec, qui ne s'affiche nulle part.
 */
final readonly class BlogGenerationDetailService
{
    private const GENERATION_COLUMNS = [
        'id', 'type', 'trigger', 'status', 'outcome_reason', 'topic_title', 'topic_hint', 'instructions',
        'blog_post_id', 'cost_microusd', 'started_at', 'finished_at', 'cancel_requested_at', 'created_at',
    ];

    private const STEP_COLUMNS = [
        'id', 'blog_generation_id', 'step', 'status', 'attempt', 'model', 'input_tokens', 'output_tokens',
        'web_searches', 'cost_microusd', 'cost_is_estimated', 'duration_ms',
    ];

    public function __construct(
        private GenerationPipeline $pipeline,
        private BlogGenerationPresenter $presenter,
        private BlogGenerationLabels $labels,
        private BlogAiCostFormatter $costs,
    ) {}

    /** La generation en cours, ou la prochaine a traiter s'il n'y en a pas en cours. */
    public function active(): ?BlogGenerationDetailView
    {
        $generation = BlogGeneration::query()
            ->whereIn('status', BlogGenerationStatus::activeValues())
            ->oldest('id')
            ->first(self::GENERATION_COLUMNS);

        return $generation === null ? null : $this->view($generation);
    }

    public function find(int $generationId): ?BlogGenerationDetailView
    {
        $generation = BlogGeneration::query()->find($generationId, self::GENERATION_COLUMNS);

        return $generation === null ? null : $this->view($generation);
    }

    private function view(BlogGeneration $generation): BlogGenerationDetailView
    {
        $rows = $generation->steps()->get(self::STEP_COLUMNS)->keyBy('step');

        // Les etapes du pipeline, dans leur ordre, puis celles qui ont une ligne sans y figurer (pipeline change depuis).
        $keys = collect($this->pipelineKeys($generation))->merge($rows->keys())->unique()->values();

        $failedStep = $rows->first(static fn (BlogGenerationStep $row): bool => $row->status === BlogGenerationStepStatus::Failed)?->step;

        return new BlogGenerationDetailView(
            id: $generation->id,
            trigger: $generation->trigger,
            status: $generation->status,
            outcome: $this->labels->outcome($generation->outcome_reason, $failedStep),
            advice: $this->labels->advice($generation->outcome_reason),
            subject: $this->presenter->subject($generation->topic_title, $generation->topic_hint),
            topicHint: $generation->topic_hint,
            instructions: $generation->instructions,
            createdAt: $generation->created_at->toImmutable(),
            startedAt: $generation->started_at,
            finishedAt: $generation->finished_at,
            duration: $this->presenter->elapsed($generation->started_at, $generation->finished_at),
            cost: $this->costs->dollars($generation->cost_microusd),
            cancelRequested: $generation->cancel_requested_at !== null,
            postId: $generation->blog_post_id,
            steps: $keys->map(fn (string $key): BlogGenerationStepView => $this->stepView($key, $rows->get($key)))->all(),
        );
    }

    /** @return list<string> */
    private function pipelineKeys(BlogGeneration $generation): array
    {
        try {
            return array_map(static fn (GenerationStep $step): string => $step->key(), $this->pipeline->steps($generation->type));
        } catch (BlogGenerationException) {
            return [];
        }
    }

    private function stepView(string $key, ?BlogGenerationStep $row): BlogGenerationStepView
    {
        return new BlogGenerationStepView(
            key: $key,
            label: $this->labels->step($key),
            status: $row?->status ?? BlogGenerationStepStatus::Pending,
            attempt: $row?->attempt ?? 0,
            model: $row?->model,
            inputTokens: $row?->input_tokens ?? 0,
            outputTokens: $row?->output_tokens ?? 0,
            webSearches: $row?->web_searches ?? 0,
            cost: $this->costs->dollars($row?->cost_microusd ?? 0),
            costIsEstimated: $row?->cost_is_estimated ?? false,
            duration: $row === null || $row->duration_ms === 0 ? null : $this->presenter->duration((int) round($row->duration_ms / 1_000)),
        );
    }
}
