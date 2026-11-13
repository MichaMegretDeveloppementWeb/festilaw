<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Web\Blog\BlogArticleRow;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Illuminate\Database\Query\JoinClause;

/**
 * Lecture de la PAGE d'un article publie, dans une langue. Une seule requete rend l'article, son texte
 * et le nom de sa categorie (jointures de un pour un) ; le contenu HTML n'est charge que pour la langue
 * demandee.
 *
 * Ne pas reutiliser pour les listes (PublishedBlogPostCardRepository) : elle charge le contenu entier.
 */
final readonly class PublishedBlogPostPageRepository
{
    /** L'article publie qui porte ce slug dans cette langue, avec son texte. Null sinon. */
    public function findBySlug(string $locale, string $slug): ?BlogArticleRow
    {
        $row = BlogPost::query()->toBase()
            ->join('blog_post_translations', function (JoinClause $join) use ($locale, $slug): void {
                $join->on('blog_post_translations.blog_post_id', '=', 'blog_posts.id')
                    ->where('blog_post_translations.locale', '=', $locale)
                    ->where('blog_post_translations.slug', '=', $slug);
            })
            ->leftJoin('blog_category_translations', function (JoinClause $join) use ($locale): void {
                $join->on('blog_category_translations.blog_category_id', '=', 'blog_posts.blog_category_id')
                    ->where('blog_category_translations.locale', '=', $locale);
            })
            ->where('blog_posts.status', BlogPostStatus::Published->value)
            ->first(BlogArticleRow::columns());

        return $row === null ? null : BlogArticleRow::fromRecord($row);
    }

    /**
     * Le slug de l'article dans chaque langue, pour ses equivalents (hreflang, selecteur de langue).
     *
     * @return array<string, string> locale => slug
     */
    public function slugsByLocale(int $postId): array
    {
        return BlogPostTranslation::query()
            ->where('blog_post_id', $postId)
            ->whereNotNull('slug')
            ->pluck('slug', 'locale')
            ->all();
    }
}
