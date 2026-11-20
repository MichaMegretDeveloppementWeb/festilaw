<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\GenerationModels;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Repositories\Blog\BlogGenerationRequeueRepository;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Relance une generation en echec. Elle repart de l'etape qui a echoue : les etapes deja terminees sont
 * gardees telles quelles (ni refaites, ni repayees), les autres retrouvent tous leurs essais. La generation
 * devient son propre lot : un nouveau message partira quand elle se terminera. Ni le plafond du mois, qui ne
 * fait qu'avertir, ni le cout estime ne l'empechent : l'article a deja ete accepte, et la reservation de
 * chaque appel le protege.
 *
 * Elle prend volontairement les modeles des reglages du moment : apres un modele retire par le fournisseur, on
 * en choisit un autre puis on relance. Son cout annonce est alors recalcule, et le plus haut des deux est garde,
 * pour que l'arret de securite ne tombe pas sous ce qu'elle a deja depense (controle du 20/11).
 */
final readonly class RetryBlogGenerationAction
{
    public function __construct(
        private BlogAiSettingsService $settings,
        private BlogGenerationCostForecaster $forecaster,
        private BlogGenerationRequeueRepository $requeue,
    ) {}

    public function execute(int $generationId): void
    {
        $generation = BlogGeneration::query()->find($generationId, ['id', 'type', 'status', 'topic_hint', 'payload', 'forecast_low_microusd', 'forecast_high_microusd']);

        if ($generation === null) {
            throw BlogGenerationException::notFound($generationId);
        }

        if ($generation->status !== BlogGenerationStatus::Failed) {
            throw BlogGenerationException::notRetryable($generationId, $generation->status);
        }

        $settings = $this->settings->current();
        $payload = [...($generation->payload ?? []), GenerationModels::PAYLOAD_KEY => GenerationModels::payloadFor($settings)];
        $forecast = $this->forecaster->forGeneration($settings, $generation->type, $payload, topicImposed: filled($generation->topic_hint));

        DB::transaction(function () use ($generation, $payload, $forecast): void {
            $this->requeue->resetUnfinishedSteps($generation->id);

            // Conditionnee au statut : deux clics rapproches ne relancent qu'une fois.
            BlogGeneration::query()
                ->whereKey($generation->id)
                ->where('status', BlogGenerationStatus::Failed->value)
                ->update([
                    'status' => BlogGenerationStatus::Queued->value,
                    'outcome_reason' => null,
                    'current_step' => null,
                    'last_error' => null,
                    'finished_at' => null,
                    'locked_until' => null,
                    'not_before' => null,
                    'lock_owner' => null,
                    'cancel_requested_at' => null,
                    'batch_uuid' => (string) Str::uuid(),
                    'batch_notified_at' => null,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'forecast_low_microusd' => max($forecast->lowMicrousd, (int) $generation->forecast_low_microusd),
                    'forecast_high_microusd' => max($forecast->highMicrousd, (int) $generation->forecast_high_microusd),
                ]);
        });
    }
}
