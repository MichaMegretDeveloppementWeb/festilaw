<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Admin\Blog\BlogPostListFilters;
use App\Data\Admin\Blog\BlogPostListResult;
use App\Data\Admin\Blog\BlogPostListRow;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Repositories\Blog\AdminBlogCategoryRepository;
use App\Repositories\Blog\AdminBlogPostListRepository;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Carbon\CarbonImmutable;

/**
 * Liste des articles du back-office.
 *
 * Plan de requetes (verifie par un test de budget) : 6.
 *   1-2. nombre d'articles et page de la liste
 *   3. langues redigees des articles de la page
 *   4. images principales des articles de la page (vignettes)
 *   5. categories du filtre
 *   6. nombre d'articles par statut (bande de chiffres)
 * Page vide : 4 (nombre, page, categories, decompte).
 */
final readonly class BlogPostListService
{
    /** Langue des titres, des categories et du lien public affiches dans le back-office. */
    private const LOCALE = 'fr';

    public function __construct(
        private AdminBlogPostListRepository $posts,
        private AdminBlogCategoryRepository $categories,
        // Lecture des images par identifiants : memes colonnes que pour les cartes publiques.
        private PublishedBlogPostCardRepository $images,
        private BlogImagePreviewFactory $previews,
        private LocalizedUrlGenerator $urls,
    ) {}

    public function list(BlogPostListFilters $filters, int $perPage): BlogPostListResult
    {
        $page = $this->posts->paginateForIndex($filters, $perPage);
        $rows = collect($page->items());

        $written = $this->posts->writtenLocalesOf($rows->pluck('id')->map(static fn (mixed $value): int => (int) $value)->all());
        $images = $this->images->findImages($rows->pluck('featured_image_id')->filter()->map(static fn (mixed $value): int => (int) $value)->unique()->values()->all());

        $page->setCollection($rows->map(function (object $row) use ($written, $images): BlogPostListRow {
            $status = BlogPostStatus::from((string) $row->status);
            $image = $row->featured_image_id === null ? null : $images->get((int) $row->featured_image_id);

            return new BlogPostListRow(
                id: (int) $row->id,
                title: filled($row->title_fr) ? (string) $row->title_fr : (filled($row->title_en) ? (string) $row->title_en : null),
                status: $status,
                origin: BlogPostOrigin::from((string) $row->origin),
                categoryName: $row->category_name,
                writtenLocales: $written[(int) $row->id] ?? [],
                publishedAt: $row->published_at === null ? null : CarbonImmutable::parse($row->published_at, 'UTC'),
                scheduledFor: $row->scheduled_for === null ? null : CarbonImmutable::parse($row->scheduled_for, 'UTC'),
                thumbnailUrl: $image === null ? null : $this->previews->url($image),
                publicUrl: $status === BlogPostStatus::Published && filled($row->slug_fr)
                    ? $this->urls->route('blog.show', ['slug' => $row->slug_fr], self::LOCALE)
                    : null,
            );
        }));

        $counts = $this->posts->countByStatus($filters);
        $statusCounts = [];

        foreach (BlogPostStatus::cases() as $status) {
            $statusCounts[$status->value] = $counts[$status->value] ?? 0;
        }

        return new BlogPostListResult($page, $this->categories->listForSelector(self::LOCALE), $statusCounts);
    }
}
