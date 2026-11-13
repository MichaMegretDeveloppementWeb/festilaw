<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Web\Blog\BlogPostCardRow;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogImage;
use App\Models\BlogPost;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Lecture des CARTES d'articles publies pour le site public (hub, page de categorie, accueil, articles
 * associes). Chaque methode rend exactement ce qu'une carte affiche, dans une langue : l'article est
 * joint a son texte et au nom de sa categorie (jointures de un pour un, pas de produit cartesien).
 *
 * Ne pas reutiliser pour la page d'un article (PublishedBlogPostPageRepository) ni pour le back-office.
 */
final readonly class PublishedBlogPostCardRepository
{
    /** Existe-t-il au moins un article publie ? (lien "Blog" de la navigation) */
    public function existsPublished(): bool
    {
        return BlogPost::query()->where('status', BlogPostStatus::Published)->exists();
    }

    public function countPublished(?int $categoryId = null): int
    {
        return BlogPost::query()
            ->where('status', BlogPostStatus::Published)
            ->when($categoryId !== null, fn ($query) => $query->where('blog_category_id', $categoryId))
            ->count();
    }

    /**
     * Une tranche des articles publies, du plus recent au plus ancien.
     *
     * @return list<BlogPostCardRow>
     */
    public function listPublished(string $locale, int $offset, int $limit, ?int $categoryId = null): array
    {
        return $this->rows(
            $this->cards($locale)
                ->when($categoryId !== null, fn (Builder $query) => $query->where('blog_posts.blog_category_id', $categoryId))
                ->orderByDesc('blog_posts.published_at')
                ->orderByDesc('blog_posts.id')
                ->offset($offset)
                ->limit($limit),
        );
    }

    /**
     * Articles associes choisis a la main pour un article, dans l'ordre voulu (publies seulement).
     *
     * @return list<BlogPostCardRow>
     */
    public function listManualRelated(int $postId, string $locale, int $limit): array
    {
        return $this->rows(
            $this->cards($locale)
                ->join('blog_post_related', 'blog_post_related.related_post_id', '=', 'blog_posts.id')
                ->where('blog_post_related.blog_post_id', $postId)
                ->orderBy('blog_post_related.position')
                ->limit($limit),
        );
    }

    /**
     * Les articles les plus recents d'une categorie, sans l'article en cours de lecture.
     *
     * @return list<BlogPostCardRow>
     */
    public function listLatestInCategory(int $categoryId, int $exceptPostId, string $locale, int $limit): array
    {
        return $this->rows(
            $this->cards($locale)
                ->where('blog_posts.blog_category_id', $categoryId)
                ->where('blog_posts.id', '!=', $exceptPostId)
                ->orderByDesc('blog_posts.published_at')
                ->orderByDesc('blog_posts.id')
                ->limit($limit),
        );
    }

    /**
     * Les images demandees, en une seule lecture, indexees par identifiant.
     *
     * @param  list<int>  $imageIds
     * @return Collection<int, BlogImage>
     */
    public function findImages(array $imageIds): Collection
    {
        if ($imageIds === []) {
            return new Collection;
        }

        return BlogImage::query()
            ->whereKey($imageIds)
            ->get(['id', 'uuid', 'basename', 'variants', 'width', 'height', 'alt'])
            ->keyBy('id');
    }

    /** Article publie + son texte dans la langue + le nom et le slug de sa categorie dans la langue. */
    private function cards(string $locale): Builder
    {
        return BlogPost::query()->toBase()
            ->join('blog_post_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_post_translations.blog_post_id', '=', 'blog_posts.id')
                    ->where('blog_post_translations.locale', '=', $locale);
            })
            ->leftJoin('blog_category_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_category_translations.blog_category_id', '=', 'blog_posts.blog_category_id')
                    ->where('blog_category_translations.locale', '=', $locale);
            })
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->whereNotNull('blog_post_translations.slug')
            ->select([
                'blog_posts.id',
                'blog_posts.featured_image_id',
                'blog_posts.published_at',
                'blog_post_translations.title',
                'blog_post_translations.slug',
                'blog_post_translations.excerpt',
                'blog_category_translations.name as category_name',
                'blog_category_translations.slug as category_slug',
            ]);
    }

    /**
     * @return list<BlogPostCardRow>
     */
    private function rows(Builder $query): array
    {
        return $query->get()->map(static fn (object $row): BlogPostCardRow => new BlogPostCardRow(
            postId: (int) $row->id,
            title: (string) $row->title,
            slug: (string) $row->slug,
            excerpt: $row->excerpt,
            publishedAt: CarbonImmutable::parse($row->published_at, 'UTC'),
            featuredImageId: $row->featured_image_id === null ? null : (int) $row->featured_image_id,
            categoryName: $row->category_name,
            categorySlug: $row->category_slug,
        ))->all();
    }
}
