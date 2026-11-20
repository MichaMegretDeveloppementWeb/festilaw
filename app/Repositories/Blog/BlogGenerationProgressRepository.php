<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;

/**
 * La file des generations avance-t-elle ? Une generation qui travaille touche sa ligne a chaque etape (verrou
 * prolonge, etape courante) ; celles qui attendent derriere elle sont couvertes par elle.
 */
final readonly class BlogGenerationProgressRepository
{
    /** Le dernier mouvement parmi les generations en attente ou en cours, nul s'il n'y en a aucune. */
    public function lastActiveMoveAt(): ?CarbonImmutable
    {
        $at = BlogGeneration::query()
            ->whereIn('status', BlogGenerationStatus::activeValues())
            ->max('updated_at');

        return $at === null ? null : CarbonImmutable::parse($at);
    }
}
