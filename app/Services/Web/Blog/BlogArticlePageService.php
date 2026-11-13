<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogArticlePageData;
use App\Repositories\Blog\PublishedBlogPostPageRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Assemble la page d'un article publie, dans une langue.
 *
 * Plan de requetes (verifie par BlogQueryBudgetTest) : 5, ou 6 sans articles associes choisis.
 *   1. article publie, joint a son texte et au nom de sa categorie, par (langue, slug)
 *   2. ses slugs dans toutes les langues (equivalents hreflang)
 *   3 a 6. images et articles associes : voir BlogArticlePageAssembler
 */
final readonly class BlogArticlePageService
{
    public function __construct(
        private PublishedBlogPostPageRepository $pages,
        private BlogArticlePageAssembler $assembler,
        private LocalizedUrlGenerator $urls,
    ) {}

    /** Null quand aucun article publie ne porte ce slug dans cette langue. */
    public function page(string $locale, string $slug): ?BlogArticlePageData
    {
        $row = $this->pages->findBySlug($locale, $slug);

        if ($row === null) {
            return null;
        }

        $alternates = [];
        $slugs = $this->pages->slugsByLocale($row->postId);

        foreach ($this->urls->locales() as $alternateLocale) {
            if (isset($slugs[$alternateLocale])) {
                $alternates[$alternateLocale] = $this->urls->route('blog.show', ['slug' => $slugs[$alternateLocale]], $alternateLocale);
            }
        }

        return $this->assembler->assemble(
            $row,
            $locale,
            $this->urls->route('blog.show', ['slug' => $row->slug], $locale),
            $alternates,
        );
    }
}
