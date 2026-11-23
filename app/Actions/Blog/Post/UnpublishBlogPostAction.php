<?php

declare(strict_types=1);

namespace App\Actions\Blog\Post;

use App\Enums\Blog\BlogPostStatus;
use App\Exceptions\Blog\BlogPostException;
use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;

/**
 * Repasse un article en brouillon : depublication d'un article publie, ou annulation d'une
 * programmation. La date de premiere publication est conservee (les anciens slugs d'un article deja
 * vu restent traces). Les liens des autres articles vers lui sont retires, leur texte reste (decision de
 * Micha du 20/11). Rejouable : un brouillon reste un brouillon.
 */
final readonly class UnpublishBlogPostAction
{
    public function __construct(private DetachLinksToBlogPostAction $detachLinks) {}

    /**
     * @throws BlogPostException
     */
    public function execute(int $postId): BlogPost
    {
        return DB::transaction(function () use ($postId): BlogPost {
            $post = BlogPost::query()->lockForUpdate()->find($postId)
                ?? throw BlogPostException::notFound($postId);

            if ($post->status !== BlogPostStatus::Draft) {
                $post->update([
                    'status' => BlogPostStatus::Draft,
                    'scheduled_for' => null,
                ]);
                $this->detachLinks->execute($post->id);
            }

            return $post;
        });
    }
}
