<?php

declare(strict_types=1);

namespace App\Actions\Blog\Category;

use App\Exceptions\Blog\BlogCategoryException;
use App\Models\BlogCategory;
use Illuminate\Support\Facades\DB;

/**
 * Deplace une categorie a une nouvelle place dans l'ordre d'affichage (menu public, selecteurs). L'ordre
 * de toutes les categories est relu sous verrou puis reecrit en une seule requete : deux deplacements
 * simultanes ne peuvent pas laisser deux categories a la meme place.
 */
final readonly class ReorderBlogCategoriesAction
{
    /**
     * @param  int  $position  nouvelle place, a partir de zero
     *
     * @throws BlogCategoryException
     */
    public function execute(int $categoryId, int $position): void
    {
        DB::transaction(function () use ($categoryId, $position): void {
            $ids = BlogCategory::query()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();

            if (! in_array($categoryId, $ids, true)) {
                throw BlogCategoryException::notFound($categoryId);
            }

            $ordered = array_values(array_filter($ids, static fn (int $id): bool => $id !== $categoryId));
            array_splice($ordered, max(0, min($position, count($ordered))), 0, [$categoryId]);

            if ($ordered === $ids) {
                return;
            }

            // Une seule ecriture : CASE id WHEN ... (identifiants entiers relus en base, rien de saisi).
            $cases = implode(' ', array_map(
                static fn (int $id, int $place): string => "WHEN {$id} THEN {$place}",
                $ordered,
                array_keys($ordered),
            ));

            BlogCategory::query()->whereKey($ordered)->update([
                'position' => DB::raw("CASE id {$cases} END"),
            ]);
        });
    }
}
