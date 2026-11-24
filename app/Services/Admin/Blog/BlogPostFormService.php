<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Admin\Blog\BlogPostFormOpening;
use App\Data\Admin\Blog\BlogPostFormState;
use App\Data\Admin\Blog\BlogPostFormView;
use App\Data\Blog\BlogReadinessIssue;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Repositories\Blog\AdminBlogCategoryRepository;
use App\Repositories\Blog\AdminBlogPostFormRepository;
use App\Services\Admin\Blog\Generation\BlogGenerationAttentionPointsService;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Lectures du formulaire d'article du back-office.
 *
 * Plan de requetes (verifie par un test de budget) :
 *   - ouverture d'un article : 4 (article, textes avec contenu, images, articles lies) ;
 *   - rendu suivant : 4 (article, textes sans contenu, images, articles lies), 3 sans article lie ;
 *   - categories du selecteur : 1, a chaque rendu ;
 *   - creation : seulement les categories (plus 1 des qu'un article lie est choisi) ;
 *   - articles a proposer comme articles lies : 1, seulement pendant que la liste est ouverte ;
 *   - traduction assistee encore en cours pour l'article : 1, lue par le composant PostTranslation a chaque
 *     montage (controle du 20/11) ;
 *   - points d'attention d'un brouillon genere : 1, a l'ouverture seulement (decision du 23/11) ;
 *   - images deja obtenues pour l'article : 1, a l'ouverture seulement (Micha, 24/11/2026) ; le navigateur tient
 *     ensuite la liste a jour.
 */
final readonly class BlogPostFormService
{
    /** Langue des titres et des categories affiches dans le back-office. */
    private const ADMIN_LOCALE = 'fr';

    /** Format du champ de date du formulaire (heure UTC). */
    public const DATETIME_FORMAT = 'Y-m-d\TH:i';

    /** Nombre d'articles proposes a l'ajout comme articles lies (les plus recents). */
    public const RELATED_CANDIDATES = 200;

    /** Largeur visee pour l'apercu d'une image dans le formulaire. */
    private const PREVIEW_WIDTH = 768;

    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private AdminBlogPostFormRepository $posts,
        private AdminBlogCategoryRepository $categories,
        private BlogPostReadinessCalculator $readiness,
        private BlogImagePreviewFactory $previews,
        private LocalizedUrlGenerator $urls,
        private BlogGenerationAttentionPointsService $attention,
        private array $locales,
    ) {}

    /** Ouverture du formulaire d'un article existant. Null s'il n'existe pas. */
    public function open(int $postId): ?BlogPostFormOpening
    {
        $post = $this->posts->findForOpening($postId);

        if ($post === null) {
            return null;
        }

        $view = $this->view($post);
        $texts = $contents = [];

        foreach ($this->locales as $locale) {
            $translation = $post->translationFor($locale);

            $texts[$locale] = [
                'title' => (string) $translation?->title,
                'slug' => (string) $translation?->slug,
                'excerpt' => (string) $translation?->excerpt,
                'metaTitle' => (string) $translation?->meta_title,
                'metaDescription' => (string) $translation?->meta_description,
            ];
            $contents[$locale] = is_array($translation?->content) ? $translation->content : null;
        }

        return new BlogPostFormOpening(
            state: new BlogPostFormState(
                postId: $post->id,
                texts: $texts,
                categoryId: $post->blog_category_id,
                featuredImageId: $post->featured_image_id,
                shareImageId: $post->share_image_id,
                relatedIds: array_column($view->related, 'id'),
                sources: array_values(array_map(
                    static fn (array $source): array => ['label' => (string) ($source['label'] ?? ''), 'url' => (string) ($source['url'] ?? '')],
                    $post->sources ?? [],
                )),
                scheduledFor: $post->scheduled_for?->format(self::DATETIME_FORMAT) ?? '',
            ),
            view: $view,
            contents: $contents,
            // Le lien de l'e-mail mene droit ici : les doutes de la verification y sont montres aussi.
            attentionPoints: $post->origin === BlogPostOrigin::Ai && $post->generation_id !== null ? $this->attention->of($post->generation_id) : [],
            obtainedImages: $this->obtainedImages($post->id),
        );
    }

    /**
     * Affichage d'un article enregistre, relu a chaque rendu. Null s'il n'existe plus.
     *
     * @param  list<int>  $relatedIds  articles lies tels que le formulaire les tient (enregistres ou non)
     */
    public function current(int $postId, array $relatedIds): ?BlogPostFormView
    {
        $post = $this->posts->findForStatus($postId);

        return $post === null ? null : $this->view($post, $relatedIds);
    }

    /**
     * Articles lies choisis pour un article pas encore enregistre, dans leur ordre.
     *
     * @param  list<int>  $relatedIds
     * @return list<array{id: int, title: ?string, status: BlogPostStatus}>
     */
    public function related(array $relatedIds): array
    {
        return $this->relatedRows($this->posts->listByIds($relatedIds));
    }

    /**
     * Articles a proposer comme articles lies : tous statuts, les plus recents d'abord, hors ceux
     * deja choisis et l'article lui-meme. Le libelle dit le statut de ce qui n'est pas publie.
     *
     * @param  list<int>  $excludedIds
     * @return array<int, string> identifiant => libelle
     */
    public function relatedCandidates(array $excludedIds): array
    {
        $options = [];

        foreach ($this->posts->listRelatedCandidates($excludedIds, '', self::RELATED_CANDIDATES) as $row) {
            $status = BlogPostStatus::from($row->status);
            $title = $row->title ?? __('Sans titre');

            $options[$row->id] = $status === BlogPostStatus::Published ? $title : $title.' · '.$status->label();
        }

        return $options;
    }

    /**
     * L'image trouvee ou creee depuis l'article (24/11/2026), telle que le formulaire la garde, si elle est encore
     * libre ou deja a cet article, et qu'elle n'a pas ete obtenue pour un autre article (« Images déjà obtenues »).
     *
     * @return array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}
     *
     * @throws BlogImageException quand l'image n'existe plus ou appartient a un autre article
     */
    public function usableImageSummary(int $imageId, ?int $postId): array
    {
        $image = $this->posts->findImage($imageId) ?? throw BlogImageException::notFound($imageId);
        $obtainedFor = $image->getAttribute('obtained_for_post_id');

        if (($image->blog_post_id !== null && $image->blog_post_id !== $postId)
            || ($obtainedFor !== null && (int) $obtainedFor !== $postId)) {
            throw BlogImageException::notUsableByPost($imageId);
        }

        return $this->imageSummary($image);
    }

    /**
     * Les images deja obtenues pour l'article, la plus recente d'abord : leur apercu, leur origine et leur date.
     *
     * @return list<array{image: array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}, origin: string, date: string}>
     */
    private function obtainedImages(int $postId): array
    {
        return $this->posts->listObtainedImages($postId)->map(fn (BlogImage $image): array => [
            'image' => $this->imageSummary($image),
            'origin' => $image->credit_source->label(),
            'date' => $image->created_at?->format('d/m/Y') ?? '',
        ])->values()->all();
    }

    /**
     * Ce que le formulaire garde d'une image : son apercu, ses descriptions dans chaque langue, l'adresse du
     * fichier a partir duquel elle se recadre, et son cadrage en place (en pixels de ce fichier ; nul quand
     * l'image n'a jamais ete recadree, le cadre couvre alors toute l'image).
     *
     * @return array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}
     */
    public function imageSummary(BlogImage $image): array
    {
        $alt = [];

        foreach ($this->locales as $locale) {
            $alt[$locale] = $image->altFor($locale);
        }

        return [
            'id' => $image->id,
            'url' => $this->previews->url($image, self::PREVIEW_WIDTH),
            'alt' => $alt,
            'source' => $this->previews->sourceUrl($image),
            'crop' => $image->hasSource() ? $image->crop : null,
        ];
    }

    /**
     * Categories du selecteur, dans leur ordre.
     *
     * @return array<int, string> identifiant => nom
     */
    public function categoryOptions(): array
    {
        return $this->categories->listForSelector(self::ADMIN_LOCALE);
    }

    /**
     * @param  list<int>|null  $relatedIds  null : les articles lies enregistres (ouverture du formulaire)
     */
    private function view(BlogPost $post, ?array $relatedIds = null): BlogPostFormView
    {
        $readiness = $this->readiness->calculate($post);
        $label = static fn (BlogReadinessIssue $issue): string => $issue->label();

        $publicUrls = [];

        if ($post->status === BlogPostStatus::Published) {
            foreach ($this->locales as $locale) {
                $slug = $post->translationFor($locale)?->slug;

                if (filled($slug)) {
                    $publicUrls[$locale] = $this->urls->route('blog.show', ['slug' => $slug], $locale);
                }
            }
        }

        $images = [];

        /** @var BlogImage $image */
        foreach ($post->images as $image) {
            $images[$image->id] = $this->imageSummary($image);
        }

        return new BlogPostFormView(
            status: $post->status,
            origin: $post->origin,
            publishedAt: $post->published_at,
            scheduledFor: $post->scheduled_for,
            blockers: array_values(array_unique(array_map($label, $readiness->blockers))),
            warnings: array_values(array_unique(array_map($label, $readiness->warnings))),
            publicUrls: $publicUrls,
            related: $this->relatedRows($relatedIds === null ? $this->posts->listRelated($post->id) : $this->posts->listByIds($relatedIds)),
            images: $images,
            generationId: $post->generation_id,
            missing: array_values(array_unique(array_map(
                static fn (BlogReadinessIssue $issue): string => $issue->locale === null ? $issue->field : $issue->field.':'.$issue->locale,
                $readiness->blockers,
            ))),
        );
    }

    /**
     * @param  list<object{id: int, title: ?string, status: string}>  $rows
     * @return list<array{id: int, title: ?string, status: BlogPostStatus}>
     */
    private function relatedRows(array $rows): array
    {
        return array_map(
            static fn (object $row): array => ['id' => $row->id, 'title' => $row->title, 'status' => BlogPostStatus::from($row->status)],
            $rows,
        );
    }
}
