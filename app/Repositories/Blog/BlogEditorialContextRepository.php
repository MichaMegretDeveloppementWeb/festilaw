<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Query\JoinClause;

/**
 * Ce que le redacteur d'un article genere doit connaitre du blog : les categories (nom anglais, a defaut
 * francais) et les articles publies qu'il peut citer ou proposer en lecture associee (titre et extrait
 * anglais). Une requete chacun, sans relire aucun contenu.
 */
final class BlogEditorialContextRepository
{
    /** @return list<array{id: int, name: string}> */
    public function categories(): array
    {
        $translation = static fn (string $locale): callable => static function (JoinClause $join) use ($locale): void {
            $join->on("{$locale}.blog_category_id", '=', 'blog_categories.id')->where("{$locale}.locale", '=', $locale);
        };

        return BlogCategory::query()->toBase()
            ->leftJoin('blog_category_translations as en', $translation('en'))
            ->leftJoin('blog_category_translations as fr', $translation('fr'))
            ->orderBy('blog_categories.position')
            ->get(['blog_categories.id', 'en.name as name_en', 'fr.name as name_fr'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) ($row->name_en ?: $row->name_fr)])
            ->filter(static fn (array $category): bool => $category['name'] !== '')
            ->values()
            ->all();
    }

    /** @return list<array{id: int, title: string, excerpt: string|null}> */
    public function publishedPosts(int $limit = 200): array
    {
        return BlogPost::query()->toBase()
            ->join('blog_post_translations as en', static function (JoinClause $join): void {
                $join->on('en.blog_post_id', '=', 'blog_posts.id')->where('en.locale', '=', 'en');
            })
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->orderByDesc('blog_posts.published_at')
            ->limit($limit)
            ->get(['blog_posts.id', 'en.title', 'en.excerpt'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'title' => (string) $row->title, 'excerpt' => $row->excerpt])
            ->all();
    }
}
