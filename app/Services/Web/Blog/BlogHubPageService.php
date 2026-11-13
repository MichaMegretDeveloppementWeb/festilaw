<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogCategoryLinkData;
use App\Data\Web\Blog\BlogHubPageData;
use App\Data\Web\Blog\BlogPaginationData;
use App\Repositories\Blog\PublicBlogCategoryRepository;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Assemble la page d'accueil du blog, dans une langue.
 *
 * Plan de requetes (verifie par BlogQueryBudgetTest) : 4.
 *   1. nombre d'articles publies
 *   2. articles de la page, joints a leur texte et au nom de leur categorie
 *   3. leurs images principales, en une fois
 *   4. categories ayant un article publie (menu)
 * Sans article publie : 1 seule requete.
 */
final readonly class BlogHubPageService
{
    public function __construct(
        private PublishedBlogPostCardRepository $posts,
        private PublicBlogCategoryRepository $categories,
        private BlogPostCardService $cards,
        private BlogHubPageWindow $window,
        private LocalizedUrlGenerator $urls,
    ) {}

    /** Null quand la page demandee n'existe pas (au-dela de la derniere). */
    public function page(string $locale, int $page): ?BlogHubPageData
    {
        $total = $this->posts->countPublished();
        $lastPage = $this->window->lastPage($total);

        if ($page > $lastPage) {
            return null;
        }

        $pagination = new BlogPaginationData($this->urls->route('blog.index', [], $locale), $page, $lastPage);

        if ($total === 0) {
            return new BlogHubPageData(null, [], [], $pagination, $pagination->url(1), $this->alternates(1));
        }

        $cards = $this->cards->fromRows(
            $this->posts->listPublished($locale, $this->window->offset($page), $this->window->limit($page)),
            $locale,
        );

        $categories = array_map(
            fn (array $category): BlogCategoryLinkData => new BlogCategoryLinkData(
                $category['name'],
                $this->urls->route('blog.category', ['slug' => $category['slug']], $locale),
            ),
            $this->categories->listWithPublishedPosts($locale),
        );

        return new BlogHubPageData(
            featured: $page === 1 ? array_shift($cards) : null,
            cards: $cards,
            categories: $categories,
            pagination: $pagination,
            url: $pagination->url($page),
            alternates: $this->alternates($page),
        );
    }

    /**
     * La meme page dans chaque langue : les trois langues ont toujours les memes articles, donc la
     * page N existe dans chacune.
     *
     * @return array<string, string>
     */
    private function alternates(int $page): array
    {
        $alternates = [];

        foreach ($this->urls->inAllLocales('blog.index') as $locale => $url) {
            $alternates[$locale] = $page <= 1 ? $url : $url.'?page='.$page;
        }

        return $alternates;
    }
}
