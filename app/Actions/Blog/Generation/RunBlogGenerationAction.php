<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationEffort;
use App\Data\Blog\Generation\GenerationLength;
use App\Data\Blog\Generation\GenerationSafetyStop;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Repositories\Blog\BlogGenerationLockRepository;
use App\Repositories\Blog\BlogGenerationSpendRepository;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationAlertService;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use App\Services\Blog\Generation\BlogGenerationStepRunner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Traite une generation deja prise par un worker (verrou pose) : execute dans l'ordre les etapes qui ne
 * sont pas encore terminees, puis fixe son issue. Rejouable : une generation coupee en route reprend a la
 * premiere etape non terminee, sans refaire les autres.
 *
 * Aucun processus long : passe l'heure de releve (`$handOverAt`), le worker rend la generation apres l'etape
 * en cours, et le worker suivant la reprend. Il fait toujours au moins une etape, pour que la generation
 * avance meme si la releve tarde.
 *
 * Pas de transaction d'ensemble : le traitement dure plusieurs minutes et chaque etape est enregistree des
 * qu'elle finit.
 *
 * Le seul frein de la depense est l'arret de securite : le double du haut du cout annonce au lancement
 * (GenerationSafetyStop, decision de Micha du 18/11). Une generation lancee avant cette decision n'a pas
 * d'annonce : elle la calcule et la garde au debut de son traitement. L'issue est une seule ecriture, conditionnee a la detention du verrou : si un autre worker
 * a repris la generation entre-temps, c'est lui qui la terminera.
 */
final readonly class RunBlogGenerationAction
{
    public function __construct(
        private GenerationPipeline $pipeline,
        private BlogGenerationStepRunner $runner,
        private BlogGenerationLockRepository $locks,
        private BlogGenerationSpendRepository $spend,
        private ArticleLengthRules $length,
        private GenerationSafetyStop $safetyStop,
        private BlogGenerationCostForecaster $forecaster,
        private BlogAiSettingsService $settings,
        private BlogGenerationAlertService $alerts,
        private NotifyFinishedBlogGenerationBatchAction $notifyBatch,
        private Application $app,
        private int $lockMarginSeconds,
    ) {}

    /** @param  CarbonImmutable|null  $handOverAt  heure a partir de laquelle rendre la generation entre deux etapes (nul : jamais) */
    public function execute(int $generationId, string $owner, ?CarbonImmutable $handOverAt = null): void
    {
        $generation = BlogGeneration::query()->with('steps')->find($generationId);

        if ($generation === null) {
            return;
        }

        $settings = $this->settings->current();

        try {
            $ceiling = $this->safetyStop->ceiling($this->announcedHigh($generation, $settings));
            $this->runSteps($generation, $owner, $settings, $ceiling, $handOverAt);
        } catch (Throwable $e) {
            // Dernier filet du traitement : une erreur hors d'une etape (configuration du pipeline, base) ne
            // doit pas laisser la generation « en cours » etre reprise en boucle.
            Log::error('Blog generation crashed outside of a step.', ['generation_id' => $generation->id, 'exception' => $e]);

            $this->finish($generation, $owner, $settings, BlogGenerationStatus::Failed, BlogGenerationOutcomeReason::StepFailed, $e->getMessage());
        }
    }

    /** @param  int  $ceiling  l'arret de securite de la generation, en millioniemes de dollar */
    private function runSteps(BlogGeneration $generation, string $owner, BlogAiSettings $settings, int $ceiling, ?CarbonImmutable $handOverAt): void
    {
        $stepsRun = 0;

        $outputs = $generation->steps
            ->where('status', BlogGenerationStepStatus::Completed)
            ->mapWithKeys(static fn (BlogGenerationStep $step): array => [$step->step => $step->output ?? []])
            ->all();

        foreach ($this->pipeline->steps($generation->type) as $index => $step) {
            if (array_key_exists($step->key(), $outputs)) {
                continue;
            }

            if ($this->cancelRequested($generation->id)) {
                $this->finish($generation, $owner, $settings, BlogGenerationStatus::Cancelled, BlogGenerationOutcomeReason::CancelledByUser, null);

                return;
            }

            if ($this->app->isDownForMaintenance()) {
                // Deploiement en cours : la generation est rendue telle quelle et reprise ensuite.
                $this->locks->release($generation->id, $owner);

                return;
            }

            if ($stepsRun > 0 && $handOverAt !== null && CarbonImmutable::now()->greaterThanOrEqualTo($handOverAt)) {
                // Ce worker a assez vecu : il rend la generation, le suivant la reprend a cette etape.
                $this->locks->release($generation->id, $owner);

                return;
            }

            BlogGeneration::query()->whereKey($generation->id)->update(['current_step' => $step->key()]);

            $outcome = $this->runner->run(
                $generation->id,
                $step,
                $index + 1,
                fn (int $attempt): GenerationStepContext => $this->context($generation, $settings, $outputs, $attempt, $ceiling),
                fn (): bool => $this->holdLock($generation->id, $owner, $step),
                fn (): bool => $this->budgetLeft($generation->id, $ceiling) > 0,
            );

            if ($outcome->lockLost) {
                return;
            }

            if ($outcome->overBudget) {
                Log::warning('Blog generation stopped by its safety stop, twice its announced cost.', [
                    'generation_id' => $generation->id,
                    'step' => $step->key(),
                    'ceiling_microusd' => $ceiling,
                    'left_microusd' => $this->budgetLeft($generation->id, $ceiling),
                ]);

                $this->finish($generation, $owner, $settings, BlogGenerationStatus::Abandoned, BlogGenerationOutcomeReason::SafetyStop, null);

                return;
            }

            if (! $outcome->succeeded()) {
                Log::error('Blog generation failed.', ['generation_id' => $generation->id, 'step' => $step->key(), 'exception' => $outcome->error]);

                $this->finish(
                    $generation, $owner, $settings, BlogGenerationStatus::Failed,
                    $outcome->creditExhausted ? BlogGenerationOutcomeReason::CreditExhausted : BlogGenerationOutcomeReason::StepFailed,
                    $outcome->error?->getMessage(),
                );

                return;
            }

            if ($outcome->result->stopsGeneration()) {
                $this->finish($generation, $owner, $settings, BlogGenerationStatus::Abandoned, $outcome->result->stopReason, null);

                return;
            }

            $outputs[$step->key()] = $outcome->result->output;
            $stepsRun++;
        }

        $this->finish($generation, $owner, $settings, BlogGenerationStatus::Completed, null, null);
    }

    /** @param  array<string, array<string, mixed>>  $outputs */
    private function context(BlogGeneration $generation, BlogAiSettings $settings, array $outputs, int $attempt, int $ceiling): GenerationStepContext
    {
        return new GenerationStepContext(
            generationId: $generation->id,
            type: $generation->type,
            topicHint: $generation->topic_hint,
            instructions: $generation->instructions,
            settings: $settings,
            outputs: $outputs,
            payload: $generation->payload ?? [],
            attempt: $attempt,
            budgetLeftMicrousd: $this->budgetLeft($generation->id, $ceiling),
            length: $this->length->for(GenerationLength::wordsFromPayload($generation->payload) ?? $settings->articleWords),
            effort: GenerationEffort::forRun($generation->payload),
        );
    }

    /** Ce qui reste avant l'arret de securite ; le plafond du mois ne fait qu'avertir (decision du 17/11). */
    private function budgetLeft(int $generationId, int $ceiling): int
    {
        return $ceiling - $this->spend->costOf($generationId);
    }

    /** Le haut du cout annonce, calcule et garde pour une generation lancee avant que l'annonce existe. */
    private function announcedHigh(BlogGeneration $generation, BlogAiSettings $settings): int
    {
        if ($generation->forecast_high_microusd !== null) {
            return $generation->forecast_high_microusd;
        }

        $forecast = $this->forecaster->forGeneration($settings, $generation->type, $generation->payload, topicImposed: filled($generation->topic_hint));
        BlogGeneration::query()->whereKey($generation->id)->update([
            'forecast_low_microusd' => $forecast->lowMicrousd,
            'forecast_high_microusd' => $forecast->highMicrousd,
        ]);

        return $forecast->highMicrousd;
    }

    private function holdLock(int $generationId, string $owner, GenerationStep $step): bool
    {
        return $this->locks->extend(
            $generationId,
            $owner,
            CarbonImmutable::now()->addSeconds($step->timeoutSeconds() + $this->lockMarginSeconds),
        );
    }

    private function cancelRequested(int $generationId): bool
    {
        return BlogGeneration::query()->whereKey($generationId)->whereNotNull('cancel_requested_at')->exists();
    }

    private function finish(
        BlogGeneration $generation,
        string $owner,
        BlogAiSettings $settings,
        BlogGenerationStatus $status,
        ?BlogGenerationOutcomeReason $reason,
        ?string $error,
    ): void {
        $finished = BlogGeneration::query()
            ->whereKey($generation->id)
            ->where('lock_owner', $owner)
            ->update([
                'status' => $status->value,
                'outcome_reason' => $reason?->value,
                'current_step' => null,
                'last_error' => $error,
                'finished_at' => CarbonImmutable::now(),
                'locked_until' => null,
                'lock_owner' => null,
            ]) === 1;

        if (! $finished) {
            return;
        }

        if ($reason === BlogGenerationOutcomeReason::CreditExhausted) {
            $this->alerts->creditExhausted($settings);
        }

        if ($status === BlogGenerationStatus::Failed || $status === BlogGenerationStatus::Abandoned) {
            $this->alerts->afterArticleWithoutDraft();
        }

        $this->alerts->monthlySpend($settings);
        $this->notifyBatch->execute($generation->batch_uuid);
    }
}
