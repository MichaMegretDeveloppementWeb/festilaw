<?php

declare(strict_types=1);

namespace App\Actions\Blog\Post;

use App\Data\Blog\NormalizedBlogContent;
use App\Data\Blog\SaveBlogPostData;
use App\Exceptions\BaseAppException;
use App\Exceptions\Blog\BlogPostException;
use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Blog\Content\BlogContentNormalizer;
use App\Services\Blog\Content\BlogContentRenderer;
use App\Services\Blog\Post\BlogPostImageSynchronizer;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use App\Services\Blog\Post\BlogPostSourceCleaner;
use App\Services\Blog\Post\BlogPostTranslationPreparer;
use App\Services\Blog\Slug\BlogPostSlugAssigner;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Cree ou modifie un article : ses textes dans chaque langue, sa categorie, ses images, ses articles
 * associes et ses sources. Le meme chemin sert au formulaire du back-office et a l'IA.
 *
 * Ne change pas le statut (programmer, publier et depublier sont d'autres Actions), mais refuse
 * d'enregistrer incomplet un article deja publie ou programme. Aucun fichier n'est ecrit ici : les
 * images ont ete envoyees avant, elles sont seulement rattachees.
 */
final readonly class SaveBlogPostAction
{
    /** Nombre d'articles lies qu'un article peut garder ; le formulaire n'en propose pas davantage. */
    public const MAX_RELATED_POSTS = 6;

    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private BlogContentNormalizer $normalizer,
        private BlogContentRenderer $renderer,
        private BlogPostTranslationPreparer $preparer,
        private BlogPostSourceCleaner $sources,
        private BlogPostSlugAssigner $slugs,
        private BlogPostImageSynchronizer $images,
        private BlogPostReadinessCalculator $readiness,
        private array $locales,
    ) {}

    /**
     * @throws BaseAppException
     */
    public function execute(SaveBlogPostData $data): BlogPost
    {
        // Calcul pur, avant d'ouvrir la transaction : un contenu refuse n'occupe aucun verrou.
        $contents = [];

        foreach ($this->locales as $locale) {
            $contents[$locale] = $this->normalizer->normalize($data->translation($locale)->content);
        }

        try {
            return DB::transaction(fn (): BlogPost => $this->save($data, $contents));
        } catch (UniqueConstraintViolationException $e) {
            // Deux enregistrements simultanes ont vise le meme slug : l'index unique a tranche.
            throw BlogSlugException::takenOnWrite($e);
        }
    }

    /**
     * @param  array<string, NormalizedBlogContent>  $contents
     */
    private function save(SaveBlogPostData $data, array $contents): BlogPost
    {
        $post = $this->lockOrNew($data);
        $wasPublished = $post->hasBeenPublished();

        if ($data->categoryId !== null && ! BlogCategory::query()->whereKey($data->categoryId)->exists()) {
            throw BlogPostException::categoryNotFound($data->categoryId);
        }

        $post->fill([
            'blog_category_id' => $data->categoryId,
            'sources' => $this->sources->clean($data->sources),
        ])->save();

        $images = $this->images->sync($post, array_values(array_filter([
            $data->featuredImageId,
            $data->shareImageId,
            ...array_merge(...array_map(static fn (NormalizedBlogContent $content): array => $content->imageIds, array_values($contents))),
        ])));

        $post->update([
            'featured_image_id' => $data->featuredImageId,
            'share_image_id' => $data->shareImageId,
        ]);

        $contentChanged = false;

        foreach ($this->locales as $locale) {
            $existing = $post->translationFor($locale);
            $input = $data->translation($locale);
            $attributes = $this->preparer->prepare($input, $contents[$locale]);
            $slug = $this->slugs->resolve($post->id, $locale, $input->slug, $attributes['title']);
            $html = $this->renderer->render($contents[$locale]->document, $locale, $images);

            $contentChanged = $contentChanged
                || $existing?->title !== $attributes['title']
                || $existing?->excerpt !== $attributes['excerpt']
                || $existing?->content != $attributes['content']; // comparaison de structures, jamais de JSON brut

            $values = [...$attributes, 'slug' => $slug, 'content_html' => $html === '' ? null : $html];
            $previousSlug = $existing?->slug;

            // La ligne de la langue est deja chargee quand elle existe : pas de relecture avant d'ecrire.
            $existing === null
                ? $post->translations()->create(['locale' => $locale, ...$values])
                : $existing->update($values);

            $this->slugs->record($post, $locale, $previousSlug, $slug);
        }

        $this->syncRelatedPosts($post, $data->relatedPostIds);

        if ($wasPublished && $contentChanged) {
            $post->update(['content_updated_at' => CarbonImmutable::now()]);
        }

        $post->load(['translations', 'images']);

        if ($post->status->requiresCompleteContent()) {
            $readiness = $this->readiness->calculate($post);

            if (! $readiness->isReady()) {
                throw BlogPostException::incompleteWhileLive($post->id, $readiness);
            }
        }

        return $post;
    }

    private function lockOrNew(SaveBlogPostData $data): BlogPost
    {
        if ($data->postId === null) {
            $post = new BlogPost(['origin' => $data->origin]);
            $post->setRelation('translations', $post->newCollection());

            return $post;
        }

        return BlogPost::query()->with('translations')->lockForUpdate()->find($data->postId)
            ?? throw BlogPostException::notFound($data->postId);
    }

    /**
     * Articles associes, dans l'ordre recu : l'article lui-meme, les doublons et les articles qui
     * n'existent plus sont ecartes.
     *
     * @param  list<int>  $relatedPostIds
     */
    private function syncRelatedPosts(BlogPost $post, array $relatedPostIds): void
    {
        $wanted = array_values(array_unique(array_filter($relatedPostIds, static fn (int $id): bool => $id !== $post->id)));
        $existing = $wanted === [] ? [] : BlogPost::query()->whereKey($wanted)->pluck('id')->all();

        $sync = [];

        foreach (array_slice(array_values(array_intersect($wanted, $existing)), 0, self::MAX_RELATED_POSTS) as $position => $relatedId) {
            $sync[$relatedId] = ['position' => $position];
        }

        $post->relatedPosts()->sync($sync);
    }
}
