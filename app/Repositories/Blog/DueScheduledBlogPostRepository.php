<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogPost;
use Carbon\CarbonImmutable;

/**
 * Articles programmes dont l'heure de publication est passee. Requete lancee chaque minute par la
 * commande planifiee : elle s'appuie sur l'index (status, scheduled_for) et ne rend que des
 * identifiants.
 */
final readonly class DueScheduledBlogPostRepository
{
    /**
     * @return list<int> identifiants, du plus ancien au plus recent
     */
    public function dueIds(CarbonImmutable $now, int $limit): array
    {
        return BlogPost::query()
            ->where('status', BlogPostStatus::Scheduled)
            ->where('scheduled_for', '<=', $now)
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }
}
