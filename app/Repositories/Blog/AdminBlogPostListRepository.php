<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Admin\Blog\BlogPostListFilters;
use App\Enums\Blog\BlogPostListSort;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Lecture de la liste des articles du back-office. Ne sert qu'a cet ecran : le formulaire, l'apercu et
 * les pages publiques ont leurs propres lectures.
 */
final readonly class AdminBlogPostListRepository
{
    /**
     * Page de la liste : une ligne par article, avec son titre francais (a defaut anglais), son adresse
     * francaise et le nom francais de sa categorie. Triee par la colonne demandee, puis du plus recent
     * au plus ancien. 2 requetes (nombre, page).
     *
     * @return LengthAwarePaginator<int, object>
     */
    public function paginateForIndex(BlogPostListFilters $filters, int $perPage): LengthAwarePaginator
    {
        $direction = $filters->descending ? 'desc' : 'asc';

        // Le titre et la date affiches sont des valeurs de repli : le tri suit ce que la liste montre.
        $order = match ($filters->sort) {
            BlogPostListSort::Title => "coalesce(nullif(fr.title, ''), en.title)",
            BlogPostListSort::Status => 'blog_posts.status',
            BlogPostListSort::Category => 'category.name',
            BlogPostListSort::Date => 'coalesce(blog_posts.published_at, blog_posts.scheduled_for)',
            BlogPostListSort::Recent => null,
        };

        return BlogPost::query()->toBase()
            ->leftJoin('blog_post_translations as fr', $this->translation('fr'))
            ->leftJoin('blog_post_translations as en', $this->translation('en'))
            ->leftJoin('blog_category_translations as category', function (JoinClause $join): void {
                $join->on('category.blog_category_id', '=', 'blog_posts.blog_category_id')
                    ->where('category.locale', '=', 'fr');
            })
            ->when($filters->status !== '', fn (Builder $query) => $query->where('blog_posts.status', $filters->status))
            ->when($filters->origin !== '', fn (Builder $query) => $query->where('blog_posts.origin', $filters->origin))
            ->when($filters->categoryId !== null, fn (Builder $query) => $query->where('blog_posts.blog_category_id', $filters->categoryId))
            ->when(trim($filters->search) !== '', fn (Builder $query) => $query->whereExists(
                fn (Builder $exists) => $exists->from('blog_post_translations as searched')
                    ->whereColumn('searched.blog_post_id', 'blog_posts.id')
                    ->where('searched.title', 'like', '%'.addcslashes(trim($filters->search), '%_\\').'%'),
            ))
            ->when($order !== null, fn (Builder $query) => $query->orderByRaw("{$order} {$direction}"))
            ->orderBy('blog_posts.id', $order === null ? $direction : 'desc')
            ->paginate($perPage, [
                'blog_posts.id',
                'blog_posts.status',
                'blog_posts.origin',
                'blog_posts.featured_image_id',
                'blog_posts.published_at',
                'blog_posts.scheduled_for',
                'fr.title as title_fr',
                'en.title as title_en',
                'fr.slug as slug_fr',
                'category.name as category_name',
            ]);
    }

    /**
     * Langues redigees (titre, adresse et contenu) de chaque article de la page, en une requete.
     *
     * @param  list<int>  $postIds
     * @return array<int, list<string>> identifiant de l'article => langues
     */
    public function writtenLocalesOf(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $written = [];

        $rows = BlogPostTranslation::query()->toBase()
            ->whereIn('blog_post_id', $postIds)
            ->whereNotNull('title')->where('title', '!=', '')
            ->whereNotNull('slug')->where('slug', '!=', '')
            ->where('word_count', '>', 0)
            ->get(['blog_post_id', 'locale']);

        foreach ($rows as $row) {
            $written[(int) $row->blog_post_id][] = (string) $row->locale;
        }

        return $written;
    }

    private function translation(string $locale): callable
    {
        return static function (JoinClause $join) use ($locale): void {
            $alias = $locale;

            $join->on("{$alias}.blog_post_id", '=', 'blog_posts.id')->where("{$alias}.locale", '=', $locale);
        };
    }
}
