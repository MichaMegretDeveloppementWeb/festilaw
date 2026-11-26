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
 * Relance une generation en echec, ou reprend une generation arretee a la main (demande de Micha du 26/11 : un
 * clic par erreur ou un arret par prudence ne doit pas faire tout repayer). Elle repart de l'etape qui a echoue ou
 * qui a ete coupee : les etapes deja terminees sont gardees telles quelles (ni refaites, ni repayees), les autres
 * retrouvent tous leurs essais. Un appel coupe par l'arret a ete facture et se refait. La generation
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

    /** @return BlogGenerationStatus le statut d'ou elle repart (en echec : relancee ; arretee : reprise) */
    public function execute(int $generationId): BlogGenerationStatus
    {
        $generation = BlogGeneration::query()->find($generationId, ['id', 'type', 'status', 'topic_hint', 'payload', 'forecast_low_microusd', 'forecast_high_microusd']);

        if ($generation === null) {
            throw BlogGenerationException::notFound($generationId);
        }

        if (! $generation->status->isRelaunchable()) {
            throw BlogGenerationException::notRetryable($generationId, $generation->status);
        }

        $settings = $this->settings->current();
        $payload = [...($generation->payload ?? []), GenerationModels::PAYLOAD_KEY => GenerationModels::payloadFor($settings)];
        $forecast = $this->forecaster->forGeneration($settings, $generation->type, $payload, topicImposed: filled($generation->topic_hint));

        DB::transaction(function () use ($generation, $payload, $forecast): void {
            $this->requeue->resetUnfinishedSteps($generation->id);
            $this->requeue->redoImagesIfGone($generation->id);

            // Conditionnee au statut : deux clics rapproches ne relancent qu'une fois.
            BlogGeneration::query()
                ->whereKey($generation->id)
                ->whereIn('status', BlogGenerationStatus::relaunchableValues())
                ->update([
                    'status' => BlogGenerationStatus::Queued->value,
                    'outcome_reason' => null,
                    'current_step' => null,
                    'finished_at' => null,
                    'locked_until' => null,
                    'not_before' => null,
                    'lock_owner' => null,
                    'cancel_requested_at' => null,
                    'cancel_requested_by' => null,
                    'batch_uuid' => (string) Str::uuid(),
                    'batch_notified_at' => null,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'forecast_low_microusd' => max($forecast->lowMicrousd, (int) $generation->forecast_low_microusd),
                    'forecast_high_microusd' => max($forecast->highMicrousd, (int) $generation->forecast_high_microusd),
                ]);
        });

        return $generation->status;
    }
}
