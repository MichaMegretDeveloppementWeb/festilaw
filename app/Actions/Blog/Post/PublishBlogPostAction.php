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
 * Publie un article, dans ses trois langues ensemble. La ligne est verrouillee et l'etat "pret" est
 * reverifie au moment de publier : un article programme peut avoir perdu son image entre-temps.
 * Rejouable : publier un article deja publie ne change rien. La date de premiere publication n'est
 * posee qu'une fois (un article depublie puis republie garde sa date d'origine).
 */
final readonly class PublishBlogPostAction
{
    public function __construct(private BlogPostReadinessCalculator $readiness) {}

    /**
     * @throws BlogPostException
     */
    public function execute(int $postId): BlogPost
    {
        return DB::transaction(function () use ($postId): BlogPost {
            $post = BlogPost::query()->with(['translations', 'images'])->lockForUpdate()->find($postId)
                ?? throw BlogPostException::notFound($postId);

            if ($post->status === BlogPostStatus::Published) {
                return $post;
            }

            $readiness = $this->readiness->calculate($post);

            if (! $readiness->isReady()) {
                throw BlogPostException::notReady($postId, $readiness);
            }

            $post->update([
                'status' => BlogPostStatus::Published,
                'published_at' => $post->published_at ?? CarbonImmutable::now(),
                'scheduled_for' => null,
            ]);

            return $post;
        });
    }
}
