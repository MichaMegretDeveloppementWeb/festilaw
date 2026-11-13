<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

/**
 * Lectures des categories pour le back-office : une methode par ecran.
 */
final readonly class AdminBlogCategoryRepository
{
    /**
     * Ecran des categories : dans leur ordre, avec leurs noms et adresses dans chaque langue et leur
     * nombre d'articles (brouillons compris). 2 requetes.
     *
     * @return Collection<int, BlogCategory>
     */
    public function listForIndex(): Collection
    {
        return BlogCategory::query()
            ->select(['id', 'position'])
            ->with(['translations:id,blog_category_id,locale,name,slug'])
            ->withCount('posts')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Selecteur de categorie (formulaire d'article, filtre de la liste) : identifiant et nom dans une
     * langue, dans l'ordre des categories. 1 requete.
     *
     * @return array<int, string> identifiant => nom
     */
    public function listForSelector(string $locale): array
    {
        return BlogCategory::query()->toBase()
            ->join('blog_category_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_category_translations.blog_category_id', '=', 'blog_categories.id')
                    ->where('blog_category_translations.locale', '=', $locale);
            })
            ->orderBy('blog_categories.position')
            ->orderBy('blog_categories.id')
            ->pluck('blog_category_translations.name', 'blog_categories.id')
            ->all();
    }

    /**
     * Formulaire d'une categorie : ses textes dans chaque langue. 2 requetes.
     */
    public function findForForm(int $categoryId): ?BlogCategory
    {
        return BlogCategory::query()
            ->select(['id', 'position'])
            ->with(['translations:id,blog_category_id,locale,name,slug,description,meta_title,meta_description'])
            ->find($categoryId);
    }
}
