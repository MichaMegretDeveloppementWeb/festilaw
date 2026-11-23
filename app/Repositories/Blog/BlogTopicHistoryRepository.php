<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Blog\Generation\BlogTopicHistory;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use App\Models\BlogPost;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * L'historique des sujets d'une generation, en trois requetes, sans relire aucun contenu. Un article donne son
 * titre anglais, a defaut francais, a defaut espagnol ; un article sans aucun titre (brouillon vide) ne compte pas.
 *
 * Le controle des doublons par le code lit TOUS les titres et TOUS les sujets proposes, refuses compris (controle
 * du 20/11) : au-dela d'une borne, un sujet refuse pouvait revenir. Seul ce que lit le modele est borne, pour que sa
 * demande ne grandisse pas sans fin : les derniers articles avec leur chapo, les titres suivants, les derniers
 * sujets proposes.
 */
final class BlogTopicHistoryRepository
{
    public const RECENT_POSTS = 100;

    public const OLDER_TITLES = 300;

    public const PROPOSED_TOPICS = 200;

    /**
     * Le sujet d'une generation arretee a la main reste libre, sauf s'il a ete refuse (decision de Micha du
     * 17/11). Celui d'une generation en echec compte, car « Relancer » peut encore la reprendre.
     *
     * @param  int|null  $exceptGenerationId  la generation en cours, dont le propre sujet n'est pas un doublon
     */
    public function load(?int $exceptGenerationId = null): BlogTopicHistory
    {
        $recent = $this->posts(withExcerpts: true)
            ->limit(self::RECENT_POSTS)
            ->get()
            ->map(fn (object $row): ?array => $this->post($row))
            ->filter()
            ->values()
            ->all();

        $recentIds = array_column($recent, 'id');
        $allPosts = $this->posts(withExcerpts: false)->get()->map(fn (object $row): ?array => $this->post($row))->filter()->values();

        $topics = BlogGeneration::query()->toBase()
            ->whereNotNull('topic_title')
            ->where(fn ($query) => $query->where('status', '!=', BlogGenerationStatus::Cancelled->value)->orWhereNotNull('topic_rejected_at'))
            ->when($exceptGenerationId !== null, fn ($query) => $query->where('id', '!=', $exceptGenerationId))
            ->orderByDesc('id')
            ->get(['topic_title', 'topic_rejected_at'])
            ->map(static fn (object $row): array => ['title' => (string) $row->topic_title, 'rejected' => $row->topic_rejected_at !== null])
            ->all();

        return new BlogTopicHistory(
            posts: $recent,
            proposedTopics: array_slice($topics, 0, self::PROPOSED_TOPICS),
            olderTitles: $allPosts->reject(static fn (array $post): bool => in_array($post['id'], $recentIds, true))->take(self::OLDER_TITLES)->pluck('title')->values()->all(),
            allTitles: [...$allPosts->pluck('title')->all(), ...array_column($topics, 'title')],
        );
    }

    /** Les articles, les plus recents d'abord, avec leurs titres (et leurs chapos pour les plus recents). */
    private function posts(bool $withExcerpts): QueryBuilder
    {
        $translation = static fn (string $locale): callable => static function (JoinClause $join) use ($locale): void {
            $join->on("{$locale}.blog_post_id", '=', 'blog_posts.id')->where("{$locale}.locale", '=', $locale);
        };

        return BlogPost::query()->toBase()
            ->leftJoin('blog_post_translations as en', $translation('en'))
            ->leftJoin('blog_post_translations as fr', $translation('fr'))
            ->leftJoin('blog_post_translations as es', $translation('es'))
            ->orderByDesc('blog_posts.id')
            ->select([
                'blog_posts.id', 'blog_posts.status',
                'en.title as title_en', 'fr.title as title_fr', 'es.title as title_es',
                ...($withExcerpts ? ['en.excerpt as excerpt_en', 'fr.excerpt as excerpt_fr', 'es.excerpt as excerpt_es'] : []),
            ]);
    }

    /** @return array{id: int, status: string, title: string, excerpt: string|null}|null */
    private function post(object $row): ?array
    {
        $title = $row->title_en ?: ($row->title_fr ?: $row->title_es);

        return blank($title) ? null : [
            'id' => (int) $row->id,
            'status' => (string) $row->status,
            'title' => (string) $title,
            'excerpt' => (($row->excerpt_en ?? null) ?: (($row->excerpt_fr ?? null) ?: ($row->excerpt_es ?? null))) ?: null,
        ];
    }
}
