<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogArticlePageData;
use App\Data\Web\Blog\BlogCategoryLinkData;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use App\Repositories\Blog\PublishedBlogPostPageRepository;
use App\Services\Blog\Post\BlogAuthorProvider;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Assemble la page d'un article publie, dans une langue.
 *
 * Plan de requetes (verifie par BlogQueryBudgetTest) : 5, ou 6 sans articles associes choisis.
 *   1. article publie, joint a son texte et au nom de sa categorie, par (langue, slug)
 *   2. ses slugs dans toutes les langues (equivalents hreflang)
 *   3. image principale et image de partage, en une fois
 *   4. articles associes choisis a la main
 *   5. a defaut, les plus recents de la meme categorie
 *   6. les images des articles associes, en une fois
 */
final readonly class BlogArticlePageService
{
    public function __construct(
        private PublishedBlogPostPageRepository $pages,
        private PublishedBlogPostCardRepository $posts,
        private BlogPostCardService $cards,
        private BlogPictureFactory $pictures,
        private BlogAuthorProvider $authors,
        private LocalizedUrlGenerator $urls,
        private int $relatedLimit = 3,
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

        $images = $this->posts->findImages(array_values(array_unique(array_filter([$row->featuredImageId, $row->shareImageId]))));
        $featured = $row->featuredImageId === null ? null : $images->get($row->featuredImageId);
        $picture = $featured === null ? null : $this->pictures->make($featured, $locale);

        // Image pour les reseaux sociaux : celle choisie pour le partage, sinon l'image principale.
        $share = $row->shareImageId === null ? null : $images->get($row->shareImageId);
        $sharePicture = $share === null ? null : $this->pictures->make($share, $locale);
        $sharePicture = $sharePicture?->shareUrl === null ? $picture : $sharePicture;

        $related = $this->posts->listManualRelated($row->postId, $locale, $this->relatedLimit);

        if ($related === [] && $row->categoryId !== null) {
            $related = $this->posts->listLatestInCategory($row->categoryId, $row->postId, $locale, $this->relatedLimit);
        }

        $author = $this->authors->forLocale($locale);

        return new BlogArticlePageData(
            title: $row->title,
            excerpt: $row->excerpt,
            html: $row->contentHtml,
            metaTitle: $row->metaTitle,
            metaDescription: $row->metaDescription ?? $row->excerpt,
            url: $this->urls->route('blog.show', ['slug' => $row->slug], $locale),
            alternates: $alternates,
            publishedAt: $row->publishedAt,
            updatedAt: $row->contentUpdatedAt,
            category: $row->categoryName !== null && $row->categorySlug !== null
                ? new BlogCategoryLinkData($row->categoryName, $this->urls->route('blog.category', ['slug' => $row->categorySlug], $locale))
                : null,
            picture: $picture,
            shareImageUrl: $sharePicture?->shareUrl,
            shareImageAlt: $sharePicture?->alt === '' ? null : $sharePicture?->alt,
            sources: $row->sources,
            related: $this->cards->fromRows($related, $locale),
            author: $author,
            authorUrl: $this->urls->route($author->profileRoute, [], $locale),
        );
    }
}
