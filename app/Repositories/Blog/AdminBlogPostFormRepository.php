<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogGeneration;
use App\Models\BlogImage;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Lectures du formulaire d'article du back-office. Deux coupes de l'article : a l'ouverture (avec le
 * contenu des trois langues) et a chaque aller-retour suivant (sans le contenu, qui peut peser 200 Ko
 * par langue). Ne pas reutiliser pour la liste, l'apercu ou les pages publiques.
 */
final readonly class AdminBlogPostFormRepository
{
    private const POST_COLUMNS = [
        'id', 'blog_category_id', 'featured_image_id', 'share_image_id', 'status', 'origin', 'generation_id',
        'scheduled_for', 'published_at', 'sources',
    ];

    private const IMAGE_COLUMNS = 'images:id,blog_post_id,uuid,basename,variants,width,height,crop,alt';

    /** Les images deja obtenues montrees au plus : de quoi revenir sur ses choix, sans charger tout un historique. */
    public const OBTAINED_IMAGES = 24;

    /**
     * Ouverture du formulaire : l'article, ses textes AVEC leur contenu, ses images. 3 requetes.
     */
    public function findForOpening(int $postId): ?BlogPost
    {
        return BlogPost::query()
            ->select(self::POST_COLUMNS)
            ->with([
                'translations:id,blog_post_id,locale,title,slug,excerpt,content,meta_title,meta_description,word_count',
                self::IMAGE_COLUMNS,
            ])
            ->find($postId);
    }

    /**
     * Aller-retour suivant : ce qu'il faut pour dire le statut et ce qui manque pour publier, SANS le
     * contenu des articles. 3 requetes.
     */
    public function findForStatus(int $postId): ?BlogPost
    {
        return BlogPost::query()
            ->select(self::POST_COLUMNS)
            ->with([
                'translations:id,blog_post_id,locale,title,slug,excerpt,meta_description,word_count',
                self::IMAGE_COLUMNS,
            ])
            ->find($postId);
    }

    /**
     * Articles lies choisis, dans leur ordre, avec leur titre francais (a defaut anglais) et leur
     * statut. 1 requete.
     *
     * @return list<object{id: int, title: ?string, status: string}>
     */
    public function listRelated(int $postId): array
    {
        return $this->titled(
            BlogPost::query()->toBase()
                ->join('blog_post_related', 'blog_post_related.related_post_id', '=', 'blog_posts.id')
                ->where('blog_post_related.blog_post_id', $postId)
                ->orderBy('blog_post_related.position'),
        );
    }

    /**
     * Articles designes par leurs identifiants, dans l'ordre demande, avec leur titre et leur statut :
     * les articles lies tels que le formulaire les tient, enregistres ou non. Un identifiant qui
     * n'existe plus est absent du resultat. 1 requete (aucune pour une liste vide).
     *
     * @param  list<int>  $postIds
     * @return list<object{id: int, title: ?string, status: string}>
     */
    public function listByIds(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $rows = [];

        foreach ($this->titled(BlogPost::query()->toBase()->whereIn('blog_posts.id', $postIds)) as $row) {
            $rows[$row->id] = $row;
        }

        $ordered = [];

        foreach ($postIds as $postId) {
            if (isset($rows[$postId])) {
                $ordered[] = $rows[$postId];
            }
        }

        return $ordered;
    }

    /**
     * Articles proposes a l'ajout comme articles lies : tous statuts, les plus recents d'abord, hors
     * l'article lui-meme et ceux deja choisis. 1 requete.
     *
     * @param  list<int>  $excludedIds
     * @return list<object{id: int, title: ?string, status: string}>
     */
    public function listRelatedCandidates(array $excludedIds, string $search, int $limit): array
    {
        $search = trim($search);

        return $this->titled(
            BlogPost::query()->toBase()
                ->when($excludedIds !== [], fn (Builder $query) => $query->whereNotIn('blog_posts.id', $excludedIds))
                ->when($search !== '', fn (Builder $query) => $query->whereExists(
                    fn (Builder $exists) => $exists->from('blog_post_translations as searched')
                        ->whereColumn('searched.blog_post_id', 'blog_posts.id')
                        ->where('searched.title', 'like', '%'.addcslashes($search, '%_\\').'%'),
                ))
                ->orderByDesc('blog_posts.id')
                ->limit($limit),
        );
    }

    /**
     * @return list<object{id: int, title: ?string, status: string}>
     */
    private function titled(Builder $query): array
    {
        $translation = static fn (string $locale): callable => static function (JoinClause $join) use ($locale): void {
            $join->on("{$locale}.blog_post_id", '=', 'blog_posts.id')->where("{$locale}.locale", '=', $locale);
        };

        return $query
            ->leftJoin('blog_post_translations as fr', $translation('fr'))
            ->leftJoin('blog_post_translations as en', $translation('en'))
            ->get(['blog_posts.id', 'blog_posts.status', 'fr.title as title_fr', 'en.title as title_en'])
            ->map(static fn (object $row): object => (object) [
                'id' => (int) $row->id,
                'title' => filled($row->title_fr) ? (string) $row->title_fr : (filled($row->title_en) ? (string) $row->title_en : null),
                'status' => (string) $row->status,
            ])
            ->all();
    }

    /**
     * Une image trouvee ou creee depuis l'article (24/11/2026) : de quoi montrer son apercu et dire a quel article
     * elle appartient deja, s'il y en a un, et pour quel article elle a ete obtenue (`obtained_for_post_id`). 1 requete.
     */
    public function findImage(int $imageId): ?BlogImage
    {
        return BlogImage::query()
            ->select(['id', 'blog_post_id', 'uuid', 'basename', 'variants', 'width', 'height', 'crop', 'alt', 'credit_source'])
            ->withObtainedForPost()
            ->find($imageId);
    }

    /**
     * Les images principales obtenues pour un article (Micha, 24/11/2026), par la generation qui l'a produit ou par
     * « Image automatique » depuis lui, meme remplacees : la plus recente d'abord, au plus OBTAINED_IMAGES. 1 requete.
     *
     * @return Collection<int, BlogImage>
     */
    public function listObtainedImages(int $postId): Collection
    {
        return BlogImage::query()
            ->select(['id', 'blog_post_id', 'uuid', 'basename', 'variants', 'width', 'height', 'crop', 'alt', 'credit_source', 'created_at'])
            ->whereIn('generation_id', BlogGeneration::query()->select('id')
                ->where(fn (EloquentBuilder $generation) => $generation->where('blog_post_id', $postId)->orWhere('source_blog_post_id', $postId)))
            ->orderByDesc('id')
            ->limit(self::OBTAINED_IMAGES)
            ->get();
    }
}
