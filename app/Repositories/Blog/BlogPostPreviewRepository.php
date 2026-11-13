<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Web\Blog\BlogArticleRow;
use App\Models\BlogPost;
use Illuminate\Database\Query\JoinClause;

/**
 * Lecture de l'apercu d'un article depuis le back-office : par identifiant, QUEL QUE SOIT son statut
 * (brouillon, programme, publie). Ne jamais l'utiliser pour une page publique : elle ne filtre pas
 * sur le statut.
 */
final readonly class BlogPostPreviewRepository
{
    /** L'article et son texte dans cette langue, en une requete. Null s'il n'existe pas. */
    public function findById(int $postId, string $locale): ?BlogArticleRow
    {
        $row = BlogPost::query()->toBase()
            ->join('blog_post_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_post_translations.blog_post_id', '=', 'blog_posts.id')
                    ->where('blog_post_translations.locale', '=', $locale);
            })
            ->leftJoin('blog_category_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_category_translations.blog_category_id', '=', 'blog_posts.blog_category_id')
                    ->where('blog_category_translations.locale', '=', $locale);
            })
            ->where('blog_posts.id', $postId)
            ->first(BlogArticleRow::columns());

        return $row === null ? null : BlogArticleRow::fromRecord($row);
    }
}
