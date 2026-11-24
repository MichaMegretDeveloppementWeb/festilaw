<?php

declare(strict_types=1);

namespace App\Data\Blog;

use App\Enums\Blog\BlogPostOrigin;

/**
 * Article a enregistrer (creation si `postId` est nul). Le statut n'en fait pas partie : programmer,
 * publier et depublier sont des cas d'usage distincts.
 */
final readonly class SaveBlogPostData
{
    /**
     * @param  array<string, BlogPostTranslationData>  $translations  textes par langue
     * @param  list<int>  $relatedPostIds  articles associes, dans l'ordre voulu
     * @param  list<BlogSourceData>  $sources
     * @param  BlogPostOrigin  $origin  pris en compte a la creation seulement
     * @param  int|null  $generationId  la generation qui cree ce brouillon, a la creation seulement (unique)
     * @param  list<int>  $requestIds  demandes d'image ou de traduction faites depuis l'article avant son premier
     *                                 enregistrement, a lui rattacher a la creation (24/11/2026)
     */
    public function __construct(
        public ?int $postId,
        public array $translations,
        public ?int $categoryId = null,
        public ?int $featuredImageId = null,
        public ?int $shareImageId = null,
        public array $relatedPostIds = [],
        public array $sources = [],
        public BlogPostOrigin $origin = BlogPostOrigin::Manual,
        public ?int $generationId = null,
        public array $requestIds = [],
    ) {}

    /** Textes de la langue demandee ; une langue absente equivaut a une langue vide. */
    public function translation(string $locale): BlogPostTranslationData
    {
        return $this->translations[$locale] ?? new BlogPostTranslationData;
    }
}
