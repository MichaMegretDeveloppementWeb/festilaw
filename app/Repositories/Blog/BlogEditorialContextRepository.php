<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Ce que le redacteur d'un article genere doit connaitre du blog : les articles publies qu'il peut citer ou
 * proposer en lecture associee (titre et extrait anglais), en une requete, sans relire aucun contenu. La
 * verification relit les articles que le texte cite, pour garder la phrase qui les presente. Le redacteur ne voit
 * plus les categories, que `CategoryStep` choisit une fois l'article termine (BlogCategoryFilingRepository).
 */
final class BlogEditorialContextRepository
{
    /** @return list<array{id: int, title: string, excerpt: string|null}> */
    public function publishedPosts(int $limit = 200): array
    {
        return $this->published()
            ->orderByDesc('blog_posts.published_at')
            ->limit($limit)
            ->get(['blog_posts.id', 'en.title', 'en.excerpt'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'title' => (string) $row->title, 'excerpt' => $row->excerpt])
            ->all();
    }

    /**
     * Les articles publies parmi ceux qu'un texte cite (jetons festilaw:post/ID), en une requete.
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, title: string, excerpt: string|null}>
     */
    public function publishedPostsAmong(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->published()
            ->whereIn('blog_posts.id', $ids)
            ->get(['blog_posts.id', 'en.title', 'en.excerpt'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'title' => (string) $row->title, 'excerpt' => $row->excerpt])
            ->all();
    }

    private function published(): Builder
    {
        return BlogPost::query()->toBase()
            ->join('blog_post_translations as en', static function (JoinClause $join): void {
                $join->on('en.blog_post_id', '=', 'blog_posts.id')->where('en.locale', '=', 'en');
            })
            ->where('blog_posts.status', BlogPostStatus::Published->value);
    }
}
