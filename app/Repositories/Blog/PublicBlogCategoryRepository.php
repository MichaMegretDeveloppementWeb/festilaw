<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogCategoryTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

/**
 * Lecture des categories du blog pour le site public : menu des categories et page d'une categorie.
 */
final readonly class PublicBlogCategoryRepository
{
    /**
     * Les categories qui ont au moins un article publie, dans l'ordre choisi au back-office, avec leur
     * nom et leur slug dans la langue. Une categorie sans article publie n'a pas de page : elle n'est
     * pas listee.
     *
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function listWithPublishedPosts(string $locale): array
    {
        return BlogCategoryTranslation::query()->toBase()
            ->join('blog_categories', 'blog_categories.id', '=', 'blog_category_translations.blog_category_id')
            ->where('blog_category_translations.locale', $locale)
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('blog_posts')
                    ->whereColumn('blog_posts.blog_category_id', 'blog_categories.id')
                    ->where('blog_posts.status', BlogPostStatus::Published->value);
            })
            ->orderBy('blog_categories.position')
            ->orderBy('blog_categories.id')
            ->get(['blog_categories.id', 'blog_category_translations.name', 'blog_category_translations.slug'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'slug' => (string) $row->slug])
            ->all();
    }

    /**
     * Les textes d'une categorie dans toutes les langues, indexes par langue : la page en affiche une
     * et tire des autres ses equivalents (hreflang).
     *
     * @return Collection<string, BlogCategoryTranslation>
     */
    public function translationsOf(int $categoryId): Collection
    {
        return BlogCategoryTranslation::query()
            ->where('blog_category_id', $categoryId)
            ->get(['id', 'blog_category_id', 'locale', 'name', 'slug', 'description', 'meta_title', 'meta_description'])
            ->keyBy('locale');
    }

    /** Slug actuel d'une categorie dans une langue (redirection depuis un ancien slug). */
    public function currentSlug(int $categoryId, string $locale): ?string
    {
        return BlogCategoryTranslation::query()
            ->where('blog_category_id', $categoryId)
            ->where('locale', $locale)
            ->value('slug');
    }
}
