<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Admin\Blog\BlogCategoryFormState;
use App\Data\Admin\Blog\BlogCategoryListRow;
use App\Models\BlogCategory;
use App\Repositories\Blog\AdminBlogCategoryRepository;

/**
 * Lectures des ecrans de categories du back-office.
 *
 * Plan de requetes : ecran des categories 2 ; formulaire d'une categorie 2 (0 pour une creation).
 */
final readonly class BlogCategoryAdminService
{
    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private AdminBlogCategoryRepository $categories,
        private array $locales,
    ) {}

    /**
     * @return list<BlogCategoryListRow>
     */
    public function list(): array
    {
        return $this->categories->listForIndex()
            ->map(function (BlogCategory $category): BlogCategoryListRow {
                $names = $slugs = [];

                foreach ($category->translations as $translation) {
                    $names[$translation->locale] = (string) $translation->name;
                    $slugs[$translation->locale] = (string) $translation->slug;
                }

                return new BlogCategoryListRow($category->id, $names, $slugs, (int) $category->posts_count);
            })
            ->values()
            ->all();
    }

    /** Null quand la categorie n'existe pas. */
    public function formState(int $categoryId): ?BlogCategoryFormState
    {
        $category = $this->categories->findForForm($categoryId);

        if ($category === null) {
            return null;
        }

        $texts = [];

        foreach ($this->locales as $locale) {
            $translation = $category->translations->firstWhere('locale', $locale);

            $texts[$locale] = [
                'name' => (string) $translation?->name,
                'slug' => (string) $translation?->slug,
                'description' => (string) $translation?->description,
                'metaTitle' => (string) $translation?->meta_title,
                'metaDescription' => (string) $translation?->meta_description,
            ];
        }

        return new BlogCategoryFormState($category->id, $texts);
    }
}
