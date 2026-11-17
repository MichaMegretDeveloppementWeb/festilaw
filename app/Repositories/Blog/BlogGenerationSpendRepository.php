<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;

/**
 * Ce que les generations ont coute, en millioniemes de dollar. Le cout d'une generation est la somme des
 * couts de ses etapes, recopiee sur sa ligne apres chaque etape (BlogGenerationStepRecorder) pour que le
 * journal et le cumul du mois se lisent sans jointure.
 */
final readonly class BlogGenerationSpendRepository
{
    /** Ce qu'une generation a deja coute, tous essais compris. */
    public function costOf(int $generationId): int
    {
        return (int) BlogGeneration::query()->whereKey($generationId)->value('cost_microusd');
    }

    /** Depense des generations lancees dans l'intervalle (debut compris, fin exclue). */
    public function spentMicrousdBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) BlogGeneration::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->sum('cost_microusd');
    }
}
