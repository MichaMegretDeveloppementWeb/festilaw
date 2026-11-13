<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogCategoryRetiredSlug;
use App\Models\BlogCategoryTranslation;

/**
 * Lecture des slugs de categories : disponibilite a l'enregistrement, et resolution d'une adresse
 * (slug vivant ou ancien slug).
 */
final readonly class BlogCategorySlugRepository
{
    /** Pris = slug vivant ou ancien slug d'une autre categorie. */
    public function isTaken(string $locale, string $slug, ?int $exceptCategoryId): bool
    {
        $live = BlogCategoryTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($exceptCategoryId !== null, fn ($query) => $query->where('blog_category_id', '!=', $exceptCategoryId))
            ->exists();

        if ($live) {
            return true;
        }

        return BlogCategoryRetiredSlug::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($exceptCategoryId !== null, fn ($query) => $query->where('blog_category_id', '!=', $exceptCategoryId))
            ->exists();
    }

    /** Identifiant de la categorie qui porte ce slug dans cette langue. */
    public function findCategoryId(string $locale, string $slug): ?int
    {
        $categoryId = BlogCategoryTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->value('blog_category_id');

        return $categoryId === null ? null : (int) $categoryId;
    }

    /** Identifiant de la categorie dont c'est un ancien slug (pour la redirection 301). */
    public function findRetiredCategoryId(string $locale, string $slug): ?int
    {
        $categoryId = BlogCategoryRetiredSlug::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->value('blog_category_id');

        return $categoryId === null ? null : (int) $categoryId;
    }
}
