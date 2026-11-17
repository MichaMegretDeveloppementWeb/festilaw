<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Blog\Generation\BlogTopicHistory;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use App\Models\BlogPost;
use Illuminate\Database\Query\JoinClause;

/**
 * L'historique des sujets pour l'etape « sujet » d'une generation : deux requetes, sans relire aucun
 * contenu. Un article donne son titre anglais, a defaut francais, a defaut espagnol ; un article sans aucun
 * titre (brouillon vide) ne compte pas. Les listes sont bornees, les plus recents d'abord : au-dela, un
 * doublon reste repere par le second controle de l'assemblage.
 */
final class BlogTopicHistoryRepository
{
    /**
     * Le sujet d'une generation arretee a la main reste libre, sauf s'il a ete refuse (decision de Micha du
     * 17/11). Celui d'une generation en echec compte, car « Relancer » peut encore la reprendre.
     *
     * @param  int|null  $exceptGenerationId  la generation en cours, dont le propre sujet n'est pas un doublon
     */
    public function load(int $postLimit = 500, int $topicLimit = 300, ?int $exceptGenerationId = null): BlogTopicHistory
    {
        $translation = static fn (string $locale): callable => static function (JoinClause $join) use ($locale): void {
            $join->on("{$locale}.blog_post_id", '=', 'blog_posts.id')->where("{$locale}.locale", '=', $locale);
        };

        $posts = BlogPost::query()->toBase()
            ->leftJoin('blog_post_translations as en', $translation('en'))
            ->leftJoin('blog_post_translations as fr', $translation('fr'))
            ->leftJoin('blog_post_translations as es', $translation('es'))
            ->orderByDesc('blog_posts.id')
            ->limit($postLimit)
            ->get([
                'blog_posts.id', 'blog_posts.status',
                'en.title as title_en', 'fr.title as title_fr', 'es.title as title_es',
                'en.excerpt as excerpt_en', 'fr.excerpt as excerpt_fr', 'es.excerpt as excerpt_es',
            ])
            ->map(static function (object $row): ?array {
                $title = $row->title_en ?: ($row->title_fr ?: $row->title_es);

                return blank($title) ? null : [
                    'id' => (int) $row->id,
                    'status' => (string) $row->status,
                    'title' => (string) $title,
                    'excerpt' => ($row->excerpt_en ?: ($row->excerpt_fr ?: $row->excerpt_es)) ?: null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $topics = BlogGeneration::query()->toBase()
            ->whereNotNull('topic_title')
            ->where(fn ($query) => $query->where('status', '!=', BlogGenerationStatus::Cancelled->value)->orWhereNotNull('topic_rejected_at'))
            ->when($exceptGenerationId !== null, fn ($query) => $query->where('id', '!=', $exceptGenerationId))
            ->orderByDesc('id')
            ->limit($topicLimit)
            ->get(['topic_title', 'topic_rejected_at'])
            ->map(static fn (object $row): array => ['title' => (string) $row->topic_title, 'rejected' => $row->topic_rejected_at !== null])
            ->all();

        return new BlogTopicHistory($posts, $topics);
    }
}
