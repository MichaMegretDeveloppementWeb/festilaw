<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogCategoryLinkData;
use App\Data\Web\Blog\BlogPostCardData;
use App\Data\Web\Blog\BlogPostCardRow;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Fabrique les cartes d'articles a partir des lignes lues en base : adresses dans la langue, lien de
 * categorie, image. Les images de toutes les cartes sont lues en UNE requete, jamais une par carte.
 */
final readonly class BlogPostCardService
{
    public function __construct(
        private PublishedBlogPostCardRepository $posts,
        private BlogPictureFactory $pictures,
        private LocalizedUrlGenerator $urls,
    ) {}

    /**
     * @param  list<BlogPostCardRow>  $rows
     * @return list<BlogPostCardData>
     */
    public function fromRows(array $rows, string $locale): array
    {
        if ($rows === []) {
            return [];
        }

        $images = $this->posts->findImages(array_values(array_unique(array_filter(
            array_map(static fn (BlogPostCardRow $row): ?int => $row->featuredImageId, $rows),
        ))));

        return array_map(function (BlogPostCardRow $row) use ($locale, $images): BlogPostCardData {
            $image = $row->featuredImageId === null ? null : $images->get($row->featuredImageId);

            return new BlogPostCardData(
                title: $row->title,
                url: $this->urls->route('blog.show', ['slug' => $row->slug], $locale),
                excerpt: $row->excerpt,
                publishedAt: $row->publishedAt,
                category: $row->categoryName !== null && $row->categorySlug !== null
                    ? new BlogCategoryLinkData($row->categoryName, $this->urls->route('blog.category', ['slug' => $row->categorySlug], $locale))
                    : null,
                picture: $image === null ? null : $this->pictures->make($image, $locale),
            );
        }, $rows);
    }
}
