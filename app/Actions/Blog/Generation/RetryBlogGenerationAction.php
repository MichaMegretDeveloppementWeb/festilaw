<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Relance une generation en echec. Elle repart de l'etape qui a echoue : les etapes deja terminees sont
 * gardees telles quelles (ni refaites, ni repayees), les autres retrouvent tous leurs essais. La generation
 * devient son propre lot : un nouveau message partira quand elle se terminera.
 */
final readonly class RetryBlogGenerationAction
{
    public function __construct(
        private BlogAiSettingsService $settings,
        private BlogGenerationBudget $budget,
    ) {}

    public function execute(int $generationId): void
    {
        $generation = BlogGeneration::query()->find($generationId, ['id', 'status']);

        if ($generation === null) {
            throw BlogGenerationException::notFound($generationId);
        }

        if ($generation->status !== BlogGenerationStatus::Failed) {
            throw BlogGenerationException::notRetryable($generationId, $generation->status);
        }

        $settings = $this->settings->current();

        if ($this->budget->spendCapReached($settings)) {
            throw BlogGenerationException::spendCapReached($this->budget->spentThisMonthMicrousd(), $settings->monthlySpendCapMicrousd());
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
