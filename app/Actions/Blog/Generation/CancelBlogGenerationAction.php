<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Arrete une generation tout de suite, qu'elle attende ou qu'elle tourne deja. Elle passe « arretee », son
 * verrou est libere et l'etape en cours est close. Un worker encore au milieu d'un appel au fournisseur
 * trouve son verrou repris : il abandonne le resultat (son cout reste inscrit) et ne fait rien d'autre.
 *
 * Avant le 17/11, une generation commencee finissait son etape avant de s'arreter : un worker mort ou une
 * etape bloquee laissait alors la generation « en cours » sans fin, sans moyen de forcer l'arret.
 */
final readonly class CancelBlogGenerationAction
{
    /** Erreur inscrite sur l'etape coupee (journal technique, jamais affichee telle quelle). */
    private const STEP_STOPPED = 'Stopped by hand while the step was running.';

    public function __construct(private NotifyFinishedBlogGenerationBatchAction $notifyBatch) {}

    public function execute(int $generationId, ?int $cancelledBy = null): void
    {
        $generation = BlogGeneration::query()->find($generationId, ['id', 'status', 'batch_uuid']);

        if ($generation === null) {
            throw BlogGenerationException::notFound($generationId);
        }

        if ($generation->status->isTerminal()) {
            throw BlogGenerationException::notCancellable($generationId, $generation->status);
        }

        $now = CarbonImmutable::now();

        $stopped = DB::transaction(function () use ($generationId, $cancelledBy, $now): bool {
            // Conditionnee aux statuts actifs : une generation qui vient de finir d'elle-meme n'est pas reecrite.
            $stopped = BlogGeneration::query()
                ->whereKey($generationId)
                ->whereIn('status', BlogGenerationStatus::activeValues())
                ->update([
                    'status' => BlogGenerationStatus::Cancelled->value,
                    'outcome_reason' => BlogGenerationOutcomeReason::CancelledByUser->value,
                    'current_step' => null,
                    'finished_at' => $now,
                    'cancel_requested_at' => $now,
                    'cancel_requested_by' => $cancelledBy,
                    'locked_until' => null,
                    'lock_owner' => null,
                ]) === 1;

            if ($stopped) {
                BlogGenerationStep::query()
                    ->where('blog_generation_id', $generationId)
                    ->where('status', BlogGenerationStepStatus::Running->value)
                    ->update([
                        'status' => BlogGenerationStepStatus::Failed->value,
                        'error' => self::STEP_STOPPED,
                        'finished_at' => $now,
                    ]);
            }

            return $stopped;
        });

        if (! $stopped) {
            throw BlogGenerationException::notCancellable($generationId, BlogGenerationStatus::from((string) BlogGeneration::query()->whereKey($generationId)->value('status')));
        }

        $this->notifyBatch->execute($generation->batch_uuid);
    }
}
