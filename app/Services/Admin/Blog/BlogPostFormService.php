<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Admin\Blog\BlogPostFormOpening;
use App\Data\Admin\Blog\BlogPostFormState;
use App\Data\Admin\Blog\BlogPostFormView;
use App\Data\Blog\BlogReadinessIssue;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Repositories\Blog\AdminBlogCategoryRepository;
use App\Repositories\Blog\AdminBlogPostFormRepository;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use App\Services\Web\Localization\LocalizedUrlGenerator;

/**
 * Lectures du formulaire d'article du back-office.
 *
 * Plan de requetes (verifie par un test de budget) :
 *   - ouverture d'un article : 4 (article, textes avec contenu, images, articles lies) ;
 *   - rendu suivant : 4 (article, textes sans contenu, images, articles lies) ;
 *   - categories du selecteur : 1, a chaque rendu ;
 *   - creation : seulement les categories.
 */
final readonly class BlogPostFormService
{
    /** Langue des titres et des categories affiches dans le back-office. */
    private const ADMIN_LOCALE = 'fr';

    /** Format du champ de date du formulaire (heure UTC). */
    public const DATETIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private AdminBlogPostFormRepository $posts,
        private AdminBlogCategoryRepository $categories,
        private BlogPostReadinessCalculator $readiness,
        private BlogImagePreviewFactory $previews,
        private LocalizedUrlGenerator $urls,
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
        );
    }

    /** Affichage d'un article enregistre, relu a chaque rendu. Null s'il n'existe plus. */
    public function current(int $postId): ?BlogPostFormView
    {
        $post = $this->posts->findForStatus($postId);

        return $post === null ? null : $this->view($post);
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

    private function view(BlogPost $post): BlogPostFormView
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
            $alt = [];

            foreach ($this->locales as $locale) {
                $alt[$locale] = $image->altFor($locale);
            }

            $images[$image->id] = ['url' => $this->previews->url($image, 768), 'alt' => $alt];
        }

        return new BlogPostFormView(
            status: $post->status,
            origin: $post->origin,
            publishedAt: $post->published_at,
            scheduledFor: $post->scheduled_for,
            blockers: array_values(array_unique(array_map($label, $readiness->blockers))),
            warnings: array_values(array_unique(array_map($label, $readiness->warnings))),
            publicUrls: $publicUrls,
            related: array_map(
                static fn (object $row): array => ['id' => $row->id, 'title' => $row->title, 'status' => BlogPostStatus::from($row->status)],
                $this->posts->listRelated($post->id),
            ),
            images: $images,
        );
    }
}
