<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Admin\Blog\Generation\BlogGenerationDetailView;
use App\Data\Admin\Blog\Generation\BlogGenerationStepView;
use App\Data\Blog\Generation\GenerationEffort;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationLength;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Repositories\Blog\AdminBlogGenerationJournalRepository;
use App\Repositories\Blog\AdminBlogPostFormRepository;
use App\Services\Admin\Blog\BlogFeaturedImageResultService;
use App\Services\Admin\Blog\BlogImagePreviewFactory;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogGenerationLabels;
use Illuminate\Support\Collection;

/**
 * Une generation et ses etapes, pour son ecran et pour la tuile « En cours » de la vue d'ensemble.
 *
 * Plan de requetes (verifie par un test de budget) : 2 (la generation, avec le nom de qui l'a lancee et de qui
 * l'a arretee ; ses etapes) ; 1 quand il n'y en a aucune. Les etapes pas encore commencees viennent de la
 * liste du pipeline, sans requete.
 *
 * Seules les colonnes affichees sont lues : ni ce qu'une etape a produit (le texte d'un article, relu toutes
 * les deux secondes pendant le suivi), ni la cause technique d'un echec, qui ne s'affiche nulle part.
 */
final readonly class BlogGenerationDetailService
{
    private const ACTIVE_COLUMNS = [
        'id', 'type', 'trigger', 'status', 'outcome_reason', 'topic_title', 'topic_hint', 'instructions',
        'blog_post_id', 'source_blog_post_id', 'payload', 'cost_microusd', 'forecast_low_microusd', 'forecast_high_microusd', 'started_at',
        'finished_at', 'cancel_requested_at', 'created_at',
    ];

    /** Largeur visee pour l'apercu de l'image obtenue par une demande d'image. */
    private const IMAGE_PREVIEW_WIDTH = 768;

    private const STEP_COLUMNS = [
        'id', 'blog_generation_id', 'step', 'status', 'attempt', 'model', 'input_tokens', 'output_tokens',
        'web_searches', 'cost_microusd', 'cost_is_estimated', 'duration_ms', 'summary',
    ];

    public function __construct(
        private GenerationPipeline $pipeline,
        private AdminBlogGenerationJournalRepository $generations,
        private BlogGenerationPresenter $presenter,
        private BlogGenerationLabels $labels,
        private BlogAiCostFormatter $costs,
        private BlogAiModelCatalog $models,
        private BlogGenerationAttentionPointsService $attention,
        private BlogFeaturedImageResultService $imageResults,
        private AdminBlogPostFormRepository $images,
        private BlogImagePreviewFactory $previews,
    ) {}

    /** La generation en cours, ou la prochaine a traiter s'il n'y en a pas en cours. */
    public function active(): ?BlogGenerationDetailView
    {
        $generation = BlogGeneration::query()
            ->whereIn('status', BlogGenerationStatus::activeValues())
            ->oldest('id')
            ->first(self::ACTIVE_COLUMNS);

        return $generation === null ? null : $this->view($generation);
    }

    public function find(int $generationId): ?BlogGenerationDetailView
    {
        $generation = $this->generations->findWithPeople($generationId);

        return $generation === null ? null : $this->view($generation);
    }

    private function view(BlogGeneration $generation): BlogGenerationDetailView
    {
        $rows = $generation->steps()->get(self::STEP_COLUMNS)->keyBy('step');

        // Les etapes du pipeline, dans leur ordre, puis celles qui ont une ligne sans y figurer (pipeline change depuis).
        $keys = collect($this->pipelineKeys($generation))->merge($rows->keys())->unique()->values();
        $failedStep = $rows->first(static fn (BlogGenerationStep $row): bool => $row->status === BlogGenerationStepStatus::Failed)?->step;
        $images = GenerationImageOptions::fromPayload($generation->payload);
        $words = GenerationLength::wordsFromPayload($generation->payload);

        return new BlogGenerationDetailView(
            id: $generation->id,
            trigger: $generation->trigger,
            status: $generation->status,
            launched: $this->launched($generation),
            outcome: $generation->outcome_reason === BlogGenerationOutcomeReason::CancelledByUser
                ? $this->stopped($generation, $keys, $rows)
                : $this->labels->outcome($generation->outcome_reason, $failedStep),
            advice: $this->labels->advice($generation->outcome_reason),
            subject: $this->presenter->subject($generation->getAttributes()['post_title'] ?? null, $generation->topic_title, $generation->topic_hint),
            topicHint: $generation->topic_hint,
            instructions: $generation->instructions,
            duration: $this->presenter->elapsed($generation->started_at, $generation->finished_at),
            cost: $this->costs->dollars($generation->cost_microusd),
            announced: $this->presenter->announced($generation->forecast_low_microusd, $generation->forecast_high_microusd),
            inputTokens: (int) $rows->sum('input_tokens'),
            outputTokens: (int) $rows->sum('output_tokens'),
            images: $images === null ? null : $this->presenter->images($images),
            length: $words === null ? null : $this->presenter->length($words),
            effort: GenerationEffort::fromPayload($generation->payload)?->shortLabel(),
            postId: $generation->blog_post_id,
            report: $this->report($rows),
            steps: $keys->map(fn (string $key): BlogGenerationStepView => $this->stepView($key, $rows->get($key)))->all(),
            attentionPoints: $generation->status->isTerminal() ? $this->attention->of($generation->id) : [],
            type: $generation->type,
            sourcePostId: $generation->source_blog_post_id,
            obtainedImage: $generation->type === BlogGenerationType::Image && $generation->status === BlogGenerationStatus::Completed ? $this->obtainedImage($generation->id) : null,
        );
    }

    /**
     * L'image qu'une demande d'image terminee a obtenue, pour la montrer sur sa page (Micha, 24/11/2026). 2 requetes,
     * pour une demande d'image seulement : sa sortie, puis l'image.
     *
     * @return array{url: ?string, alt: string, origin: string}|null
     */
    private function obtainedImage(int $generationId): ?array
    {
        $result = $this->imageResults->find($generationId);
        $image = $result->isReady() ? $this->images->findImage((int) $result->imageId) : null;

        return $image === null ? null : [
            'url' => $this->previews->url($image, self::IMAGE_PREVIEW_WIDTH),
            'alt' => $image->altFor('fr'),
            'origin' => $image->credit_source->label(),
        ];
    }

    /** Qui a lance la generation, et quand. Le nom n'est lu que pour l'ecran d'une generation. */
    private function launched(BlogGeneration $generation): string
    {
        $date = $this->presenter->dateTime($generation->created_at->toImmutable());
        $requester = $generation->getAttributes()['requester_name'] ?? null;

        return match (true) {
            $generation->trigger === BlogGenerationTrigger::Scheduled => __('Lancée automatiquement le :date (UTC)', ['date' => $date]),
            filled($requester) => __('Lancée par :name le :date (UTC)', ['name' => $requester, 'date' => $date]),
            default => __('Lancée à la main le :date (UTC)', ['date' => $date]),
        };
    }

    /**
     * Une generation arretee dit par qui, quand et apres quelle etape : « arretee » seul ne suffit pas a
     * comprendre ce qui s'est passe.
     *
     * @param  Collection<int, string>  $keys  etapes, dans l'ordre du pipeline
     * @param  Collection<string, BlogGenerationStep>  $rows
     */
    private function stopped(BlogGeneration $generation, Collection $keys, Collection $rows): string
    {
        $at = $generation->cancel_requested_at ?? $generation->finished_at;

        if ($at === null) {
            return BlogGenerationOutcomeReason::CancelledByUser->label();
        }

        $lastDone = $keys->last(static fn (string $key): bool => $rows->get($key)?->status === BlogGenerationStepStatus::Completed);
        $canceller = $generation->getAttributes()['canceller_name'] ?? null;
        $values = [
            'name' => $canceller,
            'date' => $this->presenter->dateTime($at),
            'step' => $lastDone === null ? null : $this->labels->step($lastDone),
        ];

        return match (true) {
            filled($canceller) && $lastDone !== null => __('Arrêtée par :name le :date (UTC), après l\'étape « :step ».', $values),
            filled($canceller) => __('Arrêtée par :name le :date (UTC), avant sa première étape.', $values),
            $lastDone !== null => __('Arrêtée le :date (UTC), après l\'étape « :step ».', $values),
            default => __('Arrêtée le :date (UTC), avant sa première étape.', $values),
        };
    }

    /**
     * Le compte rendu de l'IA : pourquoi ce sujet, ce qui a ete consulte, comment l'article a ete traite.
     * Chaque point vient de l'etape qui le sait ; un point sans texte n'apparait pas.
     *
     * @param  Collection<string, BlogGenerationStep>  $rows
     * @return list<array{label: string, text: string}>
     */
    private function report(Collection $rows): array
    {
        $points = [
            'topic' => __('Pourquoi ce sujet'),
            'research' => __('Ce qui a été consulté'),
            'writing' => __('Comment l\'article a été traité'),
        ];
        $report = [];

        foreach ($points as $step => $label) {
            $text = $rows->get($step)?->summary;

            if (filled($text)) {
                $report[] = ['key' => $step, 'label' => $label, 'text' => (string) $text];
            }
        }

        return $report;
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
            modelName: $this->models->name($row?->model),
            inputTokens: $row?->input_tokens ?? 0,
            outputTokens: $row?->output_tokens ?? 0,
            webSearches: $row?->web_searches ?? 0,
            cost: $this->costs->dollars($row?->cost_microusd ?? 0),
            costIsEstimated: $row?->cost_is_estimated ?? false,
            duration: $row === null || $row->duration_ms === 0 ? null : $this->presenter->duration((int) round($row->duration_ms / 1_000)),
            summary: $row?->summary,
        );
    }
}
