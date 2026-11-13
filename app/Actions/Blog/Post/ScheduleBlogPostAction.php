<?php

declare(strict_types=1);

namespace App\Actions\Blog\Post;

use App\Enums\Blog\BlogPostStatus;
use App\Exceptions\Blog\BlogPostException;
use App\Models\BlogPost;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Programme la publication d'un article a une date future. Memes exigences que pour publier : les
 * trois langues redigees, une categorie, une image principale. La commande planifiee
 * `festilaw:blog-publish-scheduled` le publiera le moment venu.
 */
final readonly class ScheduleBlogPostAction
{
    public function __construct(private BlogPostReadinessCalculator $readiness) {}

    /**
     * @throws BlogPostException
     */
    public function execute(int $postId, CarbonImmutable $at): BlogPost
    {
        return DB::transaction(function () use ($postId, $at): BlogPost {
            $post = BlogPost::query()->with(['translations', 'images'])->lockForUpdate()->find($postId)
                ?? throw BlogPostException::notFound($postId);

            if ($post->status === BlogPostStatus::Published) {
                throw BlogPostException::alreadyPublished($postId);
            }

            if (! $at->isFuture()) {
                throw BlogPostException::scheduleNotInFuture($postId, $at);
            }

            $readiness = $this->readiness->calculate($post);

            if (! $readiness->isReady()) {
                throw BlogPostException::notReady($postId, $readiness);
            }

            $post->update([
                'status' => BlogPostStatus::Scheduled,
                'scheduled_for' => $at->utc(),
            ]);

            return $post;
        });
    }
}
