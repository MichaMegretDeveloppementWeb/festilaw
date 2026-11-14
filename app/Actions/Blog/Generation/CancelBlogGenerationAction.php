<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;

/**
 * Arrete une generation. Encore en attente, elle est arretee tout de suite. Deja prise par un worker, elle
 * recoit une demande d'arret : le worker finit l'etape en cours (un appel deja parti est de toute facon
 * facture), puis s'arrete avant la suivante.
 */
final readonly class CancelBlogGenerationAction
{
    public function __construct(private NotifyFinishedBlogGenerationBatchAction $notifyBatch) {}

    /** @return bool vrai si la generation est arretee des maintenant, faux si elle s'arretera apres l'etape en cours */
    public function execute(int $generationId): bool
    {
        $generation = BlogGeneration::query()->find($generationId, ['id', 'status', 'batch_uuid']);

        if ($generation === null) {
            throw BlogGenerationException::notFound($generationId);
        }

        if ($generation->status->isTerminal()) {
            throw BlogGenerationException::notCancellable($generationId, $generation->status);
        }

        $now = CarbonImmutable::now();

        // Conditionnee au statut « en attente » : si un worker vient de la prendre, la mise a jour ne touche
        // rien et la demande d'arret prend le relais.
        $stoppedNow = BlogGeneration::query()
            ->whereKey($generationId)
            ->where('status', BlogGenerationStatus::Queued->value)
            ->update([
                'status' => BlogGenerationStatus::Cancelled->value,
                'outcome_reason' => BlogGenerationOutcomeReason::CancelledByUser->value,
                'finished_at' => $now,
            ]) === 1;

        if ($stoppedNow) {
            $this->notifyBatch->execute($generation->batch_uuid);

            return true;
        }

        BlogGeneration::query()->whereKey($generationId)->update(['cancel_requested_at' => $now]);

        return false;
    }
}
