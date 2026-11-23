<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogPost;
use App\Models\BlogPostTranslation;

/**
 * Lectures de l'assemblage d'un brouillon genere : le brouillon deja cree par une generation (pour rejouer
 * l'etape sans en creer un second), et les slugs des articles que le texte cite, dans chaque langue.
 */
final class BlogGeneratedDraftRepository
{
    public function postIdForGeneration(int $generationId): ?int
    {
        $id = BlogPost::query()->where('generation_id', $generationId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Ce que l'e-mail de fin de lot dit d'un brouillon genere : son extrait anglais, le nombre de sources, et le
     * nom francais de la categorie que sa generation a creee pour lui, s'il y en a une (decision du 23/11).
     *
     * @param  list<int>  $postIds
     * @return array<int, array{excerpt: string|null, sources: int, newCategory: string|null}>
     */
    public function summariesOf(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $summaries = [];
        $rows = BlogPost::query()->toBase()
            ->leftJoin('blog_post_translations as en', static fn ($join) => $join->on('en.blog_post_id', '=', 'blog_posts.id')->where('en.locale', '=', 'en'))
            ->leftJoin('blog_categories as created', static fn ($join) => $join->on('created.id', '=', 'blog_posts.blog_category_id')->whereColumn('created.generation_id', 'blog_posts.generation_id'))
            ->leftJoin('blog_category_translations as created_fr', static fn ($join) => $join->on('created_fr.blog_category_id', '=', 'created.id')->where('created_fr.locale', '=', 'fr'))
            ->whereIn('blog_posts.id', $postIds)
            ->get(['blog_posts.id', 'blog_posts.sources', 'en.excerpt', 'created_fr.name as new_category']);

        foreach ($rows as $row) {
            $sources = is_string($row->sources) ? json_decode($row->sources, true) : null;
            $summaries[(int) $row->id] = [
                'excerpt' => $row->excerpt ?: null,
                'sources' => is_array($sources) ? count($sources) : 0,
                'newCategory' => $row->new_category ?: null,
            ];
        }

        return $summaries;
    }

    /**
     * @param  list<int>  $postIds
     * @return array<int, array<string, string>> id de l'article => langue => slug
     */
    public function slugsOf(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $slugs = [];

        foreach (BlogPostTranslation::query()->toBase()->whereIn('blog_post_id', $postIds)->whereNotNull('slug')->get(['blog_post_id', 'locale', 'slug']) as $row) {
            $slugs[(int) $row->blog_post_id][(string) $row->locale] = (string) $row->slug;
        }

        return $slugs;
    }
}
