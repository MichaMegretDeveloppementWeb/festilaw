<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Remettre une generation en file. Les etapes terminees sont gardees (ni refaites, ni repayees) ; les autres
 * retrouvent tous leurs essais. Partage par la relance a la main et par la relance automatique d'une generation
 * planifiee tombee sur une panne passagere (controle du 20/11).
 */
final readonly class BlogGenerationRequeueRepository
{
    public function resetUnfinishedSteps(int $generationId): void
    {
        BlogGenerationStep::query()
            ->where('blog_generation_id', $generationId)
            ->where('status', '!=', BlogGenerationStepStatus::Completed->value)
            ->update(['status' => BlogGenerationStepStatus::Pending->value, 'attempt' => 0, 'error' => null]);
    }

    /**
     * Rend au worker une generation qu'il detient, pour la reprendre apres `$notBefore`, avec son `payload` mis
     * a jour. Faux : ce worker ne la detient plus, rien n'a change.
     *
     * @param  array<string, mixed>  $payload
     */
    public function requeueLater(int $generationId, string $owner, CarbonImmutable $notBefore, array $payload): bool
    {
        return DB::transaction(function () use ($generationId, $owner, $notBefore, $payload): bool {
            $held = BlogGeneration::query()->whereKey($generationId)->where('lock_owner', $owner)->lockForUpdate()->exists();

            if (! $held) {
                return false;
            }

            $this->resetUnfinishedSteps($generationId);

            BlogGeneration::query()->whereKey($generationId)->update([
                'status' => BlogGenerationStatus::Queued->value,
                'current_step' => null,
                'locked_until' => null,
                'lock_owner' => null,
                'not_before' => $notBefore,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            return true;
        });
    }
}
