<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogArticlePageData;
use App\Data\Web\Blog\BlogArticleRow;
use App\Data\Web\Blog\BlogCategoryLinkData;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use App\Services\Blog\Post\BlogAuthorProvider;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Assemble la page d'un article a partir de sa ligne : images, articles associes, auteur. Commun a la
 * page publique (BlogArticlePageService) et a l'apercu du back-office (BlogPostPreviewService), qui
 * ne different que par la facon de trouver l'article et par ses adresses.
 *
 * Requetes : 3, ou 4 sans articles associes choisis.
 *   - image principale et image de partage, en une fois
 *   - articles associes choisis a la main
 *   - a defaut, les plus recents de la meme categorie
 *   - les images des articles associes, en une fois
 */
final readonly class BlogArticlePageAssembler
{
    public function __construct(
        private PublishedBlogPostCardRepository $posts,
        private BlogPostCardService $cards,
        private BlogPictureFactory $pictures,
        private BlogAuthorProvider $authors,
        private LocalizedUrlGenerator $urls,
        private int $relatedLimit = 3,
    ) {}

    /**
     * @param  string  $url  adresse de la page dans cette langue
     * @param  array<string, string>  $alternates  la meme page dans chaque langue
     */
    public function assemble(BlogArticleRow $row, string $locale, string $url, array $alternates): BlogArticlePageData
    {
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

        return new BlogArticlePageData(
            title: $row->title,
            excerpt: $row->excerpt,
            html: $row->contentHtml,
            metaTitle: $row->metaTitle,
            metaDescription: $row->metaDescription ?? $row->excerpt,
            url: $url,
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
            author: $this->authors->forLocale($locale),
        );
    }
}
