<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;

/**
 * Lecture des slugs d'articles : disponibilite d'un slug a l'enregistrement, et resolution d'une
 * adresse publique (slug vivant d'un article publie, ancien slug, slug d'un article supprime).
 */
final readonly class BlogPostSlugRepository
{
    /**
     * Pris = slug vivant d'un autre article (brouillons compris) ou ancien slug encore rattache a un
     * autre article (sa redirection 301 doit rester valable). Un ancien slug sans article (410) ne
     * bloque pas.
     */
    public function isTaken(string $locale, string $slug, ?int $exceptPostId): bool
    {
        $live = BlogPostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($exceptPostId !== null, fn ($query) => $query->where('blog_post_id', '!=', $exceptPostId))
            ->exists();

        if ($live) {
            return true;
        }

        return BlogPostRetiredSlug::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereNotNull('blog_post_id')
            ->when($exceptPostId !== null, fn ($query) => $query->where('blog_post_id', '!=', $exceptPostId))
            ->exists();
    }

    /** Identifiant de l'article PUBLIE qui porte ce slug dans cette langue. */
    public function findPublishedPostId(string $locale, string $slug): ?int
    {
        $postId = BlogPostTranslation::query()
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_post_translations.locale', $locale)
            ->where('blog_post_translations.slug', $slug)
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->value('blog_post_translations.blog_post_id');

        return $postId === null ? null : (int) $postId;
    }

    /** Ancien slug enregistre pour cette langue (son `blog_post_id` est nul si l'article a ete supprime). */
    public function findRetired(string $locale, string $slug): ?BlogPostRetiredSlug
    {
        return BlogPostRetiredSlug::query()
            ->select(['id', 'blog_post_id'])
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();
    }

    /** Slug actuel d'un article dans une langue, seulement s'il est publie. */
    public function currentSlugIfPublished(int $postId, string $locale): ?string
    {
        return BlogPostTranslation::query()
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_post_translations.blog_post_id', $postId)
            ->where('blog_post_translations.locale', $locale)
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->value('blog_post_translations.slug');
    }
}
