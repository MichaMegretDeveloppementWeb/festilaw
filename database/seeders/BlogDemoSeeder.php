<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Blog\Category\SaveBlogCategoryAction;
use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Actions\Blog\Post\PublishBlogPostAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Data\Blog\BlogCategoryTranslationData;
use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\BlogSourceData;
use App\Data\Blog\SaveBlogCategoryData;
use App\Data\Blog\SaveBlogPostData;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogImageUsage;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use App\Repositories\Blog\BlogCategorySlugRepository;
use App\Services\Blog\Content\BlogContentSchema;
use App\Services\Blog\Content\BlogMarkdownConverter;
use Database\Seeders\BlogDemo\BlogDemoContent;
use Database\Seeders\BlogDemo\BlogDemoImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Articles de demonstration du blog, pour voir les pages publiques et faire les captures a valider :
 * 5 categories et 14 articles publies dans les trois langues, crees par les VRAIES Actions (image,
 * enregistrement, publication), donc avec les memes controles qu'un article saisi.
 *
 * Environnement local seulement : le seeder refuse de tourner ailleurs, et le deploiement ne lance
 * aucun seeder. Re-executable : il supprime d'abord SES articles (reconnus a leur slug anglais) et ne
 * touche a aucun autre article.
 *
 * Images : photos deposees dans project-management/festilaw/blog-et-generation-ia/demo-images/ (hors
 * depot), sinon visuels neutres fabriques. Voir BlogDemoImages. Le dossier peut etre remplace par le
 * conteneur (parametre $photoDirectory), ce que font les tests.
 *
 * Utilisation :
 *   php artisan db:seed --class=BlogDemoSeeder
 */
final class BlogDemoSeeder extends Seeder
{
    private const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    private const PHOTO_DIRECTORY = 'project-management/festilaw/blog-et-generation-ia/demo-images';

    /** @var list<string> */
    private const LOCALES = ['en', 'fr', 'es'];

    public function __construct(
        private readonly SaveBlogCategoryAction $saveCategory,
        private readonly StoreBlogImageAction $storeImage,
        private readonly SaveBlogPostAction $savePost,
        private readonly PublishBlogPostAction $publishPost,
        private readonly DeleteBlogPostAction $deletePost,
        private readonly BlogCategorySlugRepository $categorySlugs,
        private readonly BlogMarkdownConverter $markdown,
        private readonly ?string $photoDirectory = null,
    ) {}

    public function run(): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new RuntimeException('BlogDemoSeeder only runs in a local environment: demonstration articles must never reach production.');
        }

        $images = new BlogDemoImages($this->photoDirectory ?? base_path(self::PHOTO_DIRECTORY));
        $posts = BlogDemoContent::posts();

        try {
            $this->purge($posts);
            $categoryIds = $this->categories();

            // Du plus ancien au plus recent : chaque article est publie a sa date, par la vraie Action.
            foreach (array_reverse($posts, true) as $index => $post) {
                Carbon::setTestNow(now()->startOfDay()->subDays($post['days_ago'])->setTime(9, 30));

                try {
                    $this->post($post, $index, $categoryIds[$post['category']], $images);
                } finally {
                    Carbon::setTestNow();
                }
            }
        } finally {
            $images->cleanup();
        }

        $this->command?->info(count($posts).' articles de demonstration publies dans '.count($categoryIds).' categories.');
    }

    /**
     * Supprime les articles de demonstration d'une execution precedente, et les marques "n'existe plus"
     * que leur suppression laisse, pour que leurs adresses resservent.
     *
     * @param  list<array{slug: array<string, string>}>  $posts
     */
    private function purge(array $posts): void
    {
        $englishSlugs = array_map(static fn (array $post): string => $post['slug']['en'], $posts);
        $allSlugs = array_merge(...array_map(static fn (array $post): array => array_values($post['slug']), $posts));

        $postIds = BlogPostTranslation::query()
            ->where('locale', 'en')
            ->whereIn('slug', $englishSlugs)
            ->pluck('blog_post_id');

        foreach ($postIds as $postId) {
            $this->deletePost->execute((int) $postId);
        }

        BlogPostRetiredSlug::query()->whereNull('blog_post_id')->whereIn('slug', $allSlugs)->delete();
    }

    /**
     * Cree les categories de demonstration qui n'existent pas encore.
     *
     * @return array<string, int> cle de la categorie => identifiant
     */
    private function categories(): array
    {
        $ids = [];

        foreach (BlogDemoContent::categories() as $key => $category) {
            $existingId = $this->categorySlugs->findCategoryId('en', $category['slug']['en']);

            if ($existingId !== null) {
                $ids[$key] = $existingId;

                continue;
            }

            $translations = [];

            foreach (self::LOCALES as $locale) {
                $translations[$locale] = new BlogCategoryTranslationData(
                    name: $category['name'][$locale],
                    slug: $category['slug'][$locale],
                    description: $category['description'][$locale],
                );
            }

            $ids[$key] = $this->saveCategory->execute(new SaveBlogCategoryData(null, $translations))->id;
        }

        return $ids;
    }

    /**
     * @param  array{key: string, category: string, days_ago: int, inline_image?: bool, title: array<string, string>, slug: array<string, string>, excerpt: array<string, string>}  $post
     */
    private function post(array $post, int $index, int $categoryId, BlogDemoImages $images): void
    {
        $featuredId = $this->image($images, $post['key'], $index, BlogImageUsage::Featured, $post['title']['en']);
        $inlineId = ($post['inline_image'] ?? false)
            ? $this->image($images, $post['key'].'-inline', $index + 2, BlogImageUsage::Content, $post['title']['en'].' detail')
            : null;

        $translations = [];

        foreach (self::LOCALES as $locale) {
            $body = str_replace(':excerpt', $post['excerpt'][$locale], BlogDemoContent::body($locale));

            $translations[$locale] = new BlogPostTranslationData(
                title: $post['title'][$locale],
                slug: $post['slug'][$locale],
                excerpt: $post['excerpt'][$locale],
                content: $this->document($body, $inlineId),
                metaDescription: $post['excerpt'][$locale],
            );
        }

        $saved = $this->savePost->execute(new SaveBlogPostData(
            postId: null,
            translations: $translations,
            categoryId: $categoryId,
            featuredImageId: $featuredId,
            sources: [new BlogSourceData(
                'Regulation (EU) 2023/988 on general product safety (EUR-Lex)',
                'https://eur-lex.europa.eu/eli/reg/2023/988/oj',
            )],
        ));

        $this->publishPost->execute($saved->id);
    }

    private function image(BlogDemoImages $images, string $key, int $variation, BlogImageUsage $usage, string $nameHint): int
    {
        $file = $images->for($key, $variation);
        $credit = $file['credit'];

        return $this->storeImage->execute(new StoreBlogImageData(
            path: $file['path'],
            usage: $usage,
            alt: BlogDemoContent::imageAlt(),
            nameHint: $nameHint,
            creditSource: $credit === null ? BlogImageCreditSource::Upload : BlogImageCreditSource::Stock,
            creditAuthor: $credit['author'] ?? null,
            creditUrl: $credit['url'] ?? null,
            creditLicense: $credit['license'] ?? null,
        ))->id;
    }

    /**
     * Le texte en Markdown devient un document de contenu ; l'image du texte, s'il y en a une, est placee
     * apres le premier intertitre et son paragraphe (le Markdown ne porte pas d'image).
     *
     * @return array{type: string, content: list<array<string, mixed>>}|null
     */
    private function document(string $markdown, ?int $inlineImageId): ?array
    {
        $document = $this->markdown->toDocument($markdown)->document;

        if ($document === null || $inlineImageId === null) {
            return $document;
        }

        $image = ['type' => BlogContentSchema::IMAGE, 'attrs' => ['imageId' => $inlineImageId]];

        foreach ($document['content'] as $position => $node) {
            if ($node['type'] === BlogContentSchema::HEADING) {
                array_splice($document['content'], $position + 2, 0, [$image]);

                break;
            }
        }

        return $document;
    }
}
