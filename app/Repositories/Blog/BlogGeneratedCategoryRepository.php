<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogCategory;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPost;

/**
 * Les categories qu'une generation d'article cree quand aucune ne convient (decision du 23/11) : celle qui
 * porte deja le nom propose, pour ne pas la creer deux fois, et la suppression d'une categorie creee pour un
 * brouillon que l'on supprime, quand plus aucun article ne l'emploie. Une requete chacune.
 */
final class BlogGeneratedCategoryRepository
{
    /** La categorie dont le nom anglais est celui-ci, sans tenir compte de la casse. */
    public function idNamed(string $name): ?int
    {
        $id = BlogCategoryTranslation::query()
            ->where('locale', 'en')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->orderBy('blog_category_id')
            ->value('blog_category_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Supprime la categorie si cette generation l'a creee et qu'aucun article ne l'emploie plus. Ses textes et
     * ses anciennes adresses partent avec elle (cles etrangeres en cascade). Une categorie creee a la main
     * n'est jamais touchee.
     */
    public function deleteIfUnusedCreatedBy(int $categoryId, int $generationId): bool
    {
        return BlogCategory::query()
            ->whereKey($categoryId)
            ->where('generation_id', $generationId)
            ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                ->from((new BlogPost)->getTable())
                ->whereColumn('blog_posts.blog_category_id', 'blog_categories.id'))
            ->delete() > 0;
    }
}
