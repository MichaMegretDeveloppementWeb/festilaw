<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogCategoryLinkData;
use App\Data\Web\Blog\BlogCategoryPageData;
use App\Data\Web\Blog\BlogPaginationData;
use App\Repositories\Blog\BlogCategorySlugRepository;
use App\Repositories\Blog\PublicBlogCategoryRepository;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Assemble la page d'une categorie du blog, dans une langue.
 *
 * Plan de requetes (verifie par BlogQueryBudgetTest) : 6.
 *   1. categorie par son slug dans la langue
 *   2. ses textes dans toutes les langues (texte de la page + equivalents hreflang)
 *   3. nombre d'articles publies de la categorie
 *   4. articles de la page, joints a leur texte
 *   5. leurs images principales, en une fois
 *   6. categories ayant un article publie (menu)
 */
final readonly class BlogCategoryPageService
{
    public function __construct(
        private BlogCategorySlugRepository $slugs,
        private PublicBlogCategoryRepository $categories,
        private PublishedBlogPostCardRepository $posts,
        private BlogPostCardService $cards,
        private LocalizedUrlGenerator $urls,
        private int $perPage = 12,
    ) {}

    /**
     * Null quand il n'y a pas de page a servir : slug inconnu, categorie sans article publie, ou page
     * au-dela de la derniere.
     */
    public function page(string $locale, string $slug, int $page): ?BlogCategoryPageData
    {
        $categoryId = $this->slugs->findCategoryId($locale, $slug);

        if ($categoryId === null) {
            return null;
        }

        $translations = $this->categories->translationsOf($categoryId);
        $translation = $translations->get($locale);
        $total = $this->posts->countPublished($categoryId);
        $lastPage = max(1, (int) ceil($total / $this->perPage));

        if ($translation === null || $total === 0 || $page > $lastPage) {
            return null;
        }

        $alternates = [];

        foreach ($this->urls->locales() as $alternateLocale) {
            $alternateSlug = $translations->get($alternateLocale)?->slug;

            if ($alternateSlug !== null) {
                $url = $this->urls->route('blog.category', ['slug' => $alternateSlug], $alternateLocale);
                $alternates[$alternateLocale] = $page <= 1 ? $url : $url.'?page='.$page;
            }
        }

        $pagination = new BlogPaginationData($this->urls->route('blog.category', ['slug' => $slug], $locale), $page, $lastPage);

        $cards = $this->cards->fromRows(
            $this->posts->listPublished($locale, ($page - 1) * $this->perPage, $this->perPage, $categoryId),
            $locale,
        );

        $menu = array_map(
            fn (array $category): BlogCategoryLinkData => new BlogCategoryLinkData(
                $category['name'],
                $this->urls->route('blog.category', ['slug' => $category['slug']], $locale),
                isCurrent: $category['id'] === $categoryId,
            ),
            $this->categories->listWithPublishedPosts($locale),
        );

        return new BlogCategoryPageData(
            name: $translation->name,
            description: $translation->description,
            metaTitle: $translation->meta_title,
            metaDescription: $translation->meta_description,
            cards: $cards,
            categories: $menu,
            pagination: $pagination,
            url: $pagination->url($page),
            alternates: $alternates,
        );
    }

    /** Adresse actuelle de la categorie dont `$slug` est un ancien slug dans cette langue, ou null. */
    public function currentUrlForRetiredSlug(string $locale, string $slug): ?string
    {
        $categoryId = $this->slugs->findRetiredCategoryId($locale, $slug);

        if ($categoryId === null) {
            return null;
        }

        $current = $this->categories->currentSlug($categoryId, $locale);

        return $current === null ? null : $this->urls->route('blog.category', ['slug' => $current], $locale);
    }
}
