<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationLength;
use App\Data\Blog\Generation\ScheduledGenerationPlan;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Repositories\Blog\ScheduledBlogGenerationRepository;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La generation automatique : une fois par periode, met en file un lot d'articles. Appelee chaque jour par
 * la tache planifiee ; elle ne fait rien tant que la periode reglee n'est pas echue depuis le dernier lot
 * lance. Un lot du mais retenu est inscrit au journal (une ligne « non lancee », sans jamais repeter deux fois
 * de suite la meme raison), et sera lance des que la raison tombe. Une seule raison le retient : trop de
 * brouillons en attente. Le cout ne retient rien : chaque generation du lot garde son cout annonce et s'arrete
 * par securite au double de son haut. Le plafond du mois avertit seulement (decisions de Micha du 17/11 et du
 * 18/11).
 *
 * Rejouable : relancee le meme jour apres avoir lance un lot, elle ne trouve plus rien de du.
 */
final readonly class PlanScheduledBlogGenerationsAction
{
    public function __construct(
        private GenerationPipeline $pipeline,
        private BlogAiSettingsService $settings,
        private BlogGenerationBudget $budget,
        private ScheduledBlogGenerationRepository $scheduled,
        private BlogGenerationCostForecaster $forecaster,
    ) {}

    /** @param  bool  $dryRun  dit ce qui serait fait, sans rien ecrire ni envoyer */
    public function execute(bool $dryRun = false): ScheduledGenerationPlan
    {
        $settings = $this->settings->current();

        if (! $settings->enabled) {
            return ScheduledGenerationPlan::disabled();
        }

        $dueAt = $this->dueAt($settings);

        if ($dueAt !== null && $dueAt->isFuture()) {
            return ScheduledGenerationPlan::notDue($dueAt);
        }

        $this->pipeline->steps(BlogGenerationType::Article);

        $count = min($settings->articlesPerRun, $this->budget->remainingDraftRoom($settings));

        if ($count === 0) {
            return $this->block(BlogGenerationOutcomeReason::PendingDraftsCapReached, $dryRun);
        }

        if (! $dryRun) {
            $this->launch($count, $settings);
        }

        return ScheduledGenerationPlan::launched($count);
    }

    /**
     * Le jour a partir duquel un lot est du, ou nul s'il n'y en a jamais eu (du tout de suite). Compare au
     * jour pres : la tache passe chaque jour a la meme heure, a quelques secondes pres.
     */
    private function dueAt(BlogAiSettings $settings): ?CarbonImmutable
    {
        $lastLaunchedAt = $this->scheduled->lastLaunchedAt();

        return $lastLaunchedAt === null
            ? null
            : $settings->periodUnit->addTo($lastLaunchedAt, $settings->periodCount)->startOfDay();
    }

    /** Chaque generation du lot porte les images, la longueur et le cout annonce des reglages, figes au lancement. */
    private function launch(int $count, BlogAiSettings $settings): void
    {
        $batchUuid = (string) Str::uuid();
        $payload = [
            GenerationImageOptions::PAYLOAD_KEY => GenerationImageOptions::fromSettings($settings)->toPayload(),
            GenerationLength::PAYLOAD_KEY => GenerationLength::payloadFor($settings->articleWords),
        ];
        $forecast = $this->forecaster->forGeneration($settings, BlogGenerationType::Article, $payload, topicImposed: false);

        DB::transaction(static function () use ($count, $batchUuid, $payload, $forecast): void {
            for ($i = 0; $i < $count; $i++) {
                BlogGeneration::query()->create([
                    'type' => BlogGenerationType::Article,
                    'trigger' => BlogGenerationTrigger::Scheduled,
                    'status' => BlogGenerationStatus::Queued,
                    'batch_uuid' => $batchUuid,
                    'payload' => $payload,
                    'forecast_low_microusd' => $forecast->lowMicrousd,
                    'forecast_high_microusd' => $forecast->highMicrousd,
                ]);
            }
        });
    }

    private function block(BlogGenerationOutcomeReason $reason, bool $dryRun): ScheduledGenerationPlan
    {
        if (! $dryRun && $this->scheduled->lastSkippedReason() !== $reason) {
            $now = CarbonImmutable::now();

            BlogGeneration::query()->create([
                'type' => BlogGenerationType::Article,
                'trigger' => BlogGenerationTrigger::Scheduled,
                'status' => BlogGenerationStatus::Abandoned,
                'outcome_reason' => $reason,
                'batch_uuid' => (string) Str::uuid(),
                'batch_notified_at' => $now,
                'finished_at' => $now,
            ]);
        }

        return ScheduledGenerationPlan::blocked($reason);
    }
}
