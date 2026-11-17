<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Relance une generation en echec. Elle repart de l'etape qui a echoue : les etapes deja terminees sont
 * gardees telles quelles (ni refaites, ni repayees), les autres retrouvent tous leurs essais. La generation
 * devient son propre lot : un nouveau message partira quand elle se terminera. Ni le plafond du mois, qui ne
 * fait qu'avertir, ni le cout estime ne l'empechent : l'article a deja ete accepte, et la reservation de
 * chaque appel le protege.
 */
final readonly class RetryBlogGenerationAction
{
    public function execute(int $generationId): void
    {
        $generation = BlogGeneration::query()->find($generationId, ['id', 'status']);

        if ($generation === null) {
            throw BlogGenerationException::notFound($generationId);
        }

        if ($generation->status !== BlogGenerationStatus::Failed) {
            throw BlogGenerationException::notRetryable($generationId, $generation->status);
        }

        DB::transaction(static function () use ($generationId): void {
            BlogGenerationStep::query()
                ->where('blog_generation_id', $generationId)
                ->where('status', '!=', BlogGenerationStepStatus::Completed->value)
                ->update(['status' => BlogGenerationStepStatus::Pending->value, 'attempt' => 0, 'error' => null]);

            // Conditionnee au statut : deux clics rapproches ne relancent qu'une fois.
            BlogGeneration::query()
                ->whereKey($generationId)
                ->where('status', BlogGenerationStatus::Failed->value)
                ->update([
                    'status' => BlogGenerationStatus::Queued->value,
                    'outcome_reason' => null,
                    'current_step' => null,
                    'last_error' => null,
                    'finished_at' => null,
                    'locked_until' => null,
                    'lock_owner' => null,
                    'cancel_requested_at' => null,
                    'batch_uuid' => (string) Str::uuid(),
                    'batch_notified_at' => null,
                ]);
        });
    }
}
