<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ce que la tache planifiee a deja fait : de quand date le dernier lot reellement lance, et quelle est la
 * derniere ligne « lot non lance » du journal. Une ligne non lancee est une generation planifiee abandonnee
 * sans avoir jamais demarre (`started_at` nul).
 */
final readonly class ScheduledBlogGenerationRepository
{
    /** Date du dernier lot lance par la tache planifiee, ou nul si elle n'en a jamais lance. */
    public function lastLaunchedAt(): ?CarbonImmutable
    {
        $createdAt = BlogGeneration::query()
            ->where('trigger', BlogGenerationTrigger::Scheduled->value)
            ->where(static function (Builder $query): void {
                $query->where('status', '!=', BlogGenerationStatus::Abandoned->value)->orWhereNotNull('started_at');
            })
            ->max('created_at');

        return $createdAt === null ? null : CarbonImmutable::parse($createdAt);
    }

    /**
     * La raison de la derniere ligne planifiee du journal, si cette ligne est un lot non lance. Sert a ne
     * pas inscrire deux fois de suite la meme raison.
     */
    public function lastSkippedReason(): ?BlogGenerationOutcomeReason
    {
        $last = BlogGeneration::query()
            ->where('trigger', BlogGenerationTrigger::Scheduled->value)
            ->latest('id')
            ->first(['id', 'status', 'outcome_reason', 'started_at']);

        if ($last === null || $last->status !== BlogGenerationStatus::Abandoned || $last->started_at !== null) {
            return null;
        }

        return $last->outcome_reason;
    }
}
