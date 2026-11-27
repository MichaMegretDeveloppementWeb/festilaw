<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Editorial;

use App\Actions\Blog\Category\SaveBlogCategoryAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\BlogCategoryTranslationData;
use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\BlogSourceData;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationFindings;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\SaveBlogCategoryData;
use App\Data\Blog\SaveBlogPostData;
use App\Enums\Blog\BlogPostOrigin;
use App\Exceptions\Blog\BlogEditorialException;
use App\Models\BlogCategory;
use App\Repositories\Blog\BlogGeneratedCategoryRepository;
use App\Repositories\Blog\BlogGeneratedDraftRepository;
use App\Services\Blog\Generation\Editorial\BlogInternalLinkResolver;
use App\Services\Blog\Generation\Editorial\EditorialDocumentBuilder;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Derniere etape : l'assemblage du brouillon (tache 6.11). Les trois versions passent par le meme chemin qu'un
 * article ecrit au back-office (`SaveBlogPostAction`) : jetons de liens traduits dans chaque langue,
 * Markdown converti en document, images placees a leurs emplacements, slugs produits depuis les titres,
 * sources du dossier, categorie, articles associes, image principale. Le brouillon est marque IA et lie a sa
 * generation.
 *
 * Quand l'etape du choix de la categorie (CategoryStep) en a propose une nouvelle, elle est creee ici avec ses
 * trois noms (le nom anglais
 * remplace un nom traduit manquant) et marquee comme creee par cette generation, dans la meme transaction que le
 * brouillon : un echec ne laisse aucune categorie orpheline. Une categorie qui porte deja ce nom est reprise.
 *
 * Rejouable : si la generation a deja son brouillon (etape rejouee apres un verrou perdu ou une erreur
 * survenue apres l'enregistrement), il est rendu tel quel, sans second brouillon. Le second controle des
 * doublons, sur le titre final, se fait desormais a la fin de l'etape du style (StyleStep, controle du 20/11).
 */
final readonly class AssembleGeneratedBlogDraftAction implements GenerationStep
{
    /** Les versions viennent de leur controle contre le texte anglais, qui les rend corrigees. */
    private const LOCALES = ['en' => 'style', 'fr' => 'version_check_fr', 'es' => 'version_check_es'];

    public function __construct(
        private SaveBlogPostAction $savePost,
        private SaveBlogCategoryAction $saveCategory,
        private BlogGeneratedDraftRepository $drafts,
        private BlogGeneratedCategoryRepository $categories,
        private BlogInternalLinkResolver $links,
        private EditorialDocumentBuilder $documents,
    ) {}

    public function key(): string
    {
        return 'assembly';
    }

    public function timeoutSeconds(): int
    {
        return 120;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $existing = $this->drafts->postIdForGeneration($context->generationId);

        if ($existing !== null) {
            return $this->ready($existing, $context);
        }

        $articles = [];

        foreach (self::LOCALES as $locale => $step) {
            $articles[$locale] = EditorialArticle::fromArray($context->output($step)['article'] ?? []);

            if ($articles[$locale]->isEmpty()) {
                throw BlogEditorialException::missingInput($this->key(), "the {$locale} article");
            }
        }

        $writing = $context->output('writing');
        $images = collect($context->output('images')['images'] ?? [])->mapWithKeys(fn (array $image): array => [(int) $image['number'] => (int) $image['image_id']])->all();
        $postSlugs = $this->drafts->slugsOf($this->citedPostIds($articles['en']->body));
        $translations = [];

        foreach ($articles as $locale => $article) {
            $translations[$locale] = new BlogPostTranslationData(
                title: $article->title,
                slug: null,
                excerpt: $article->excerpt,
                content: $this->documents->build($this->links->resolve($article->body, $locale, $postSlugs), array_map(static fn (int $id): array => ['imageId' => $id], $images)),
                metaTitle: $article->metaTitle ?: null,
                metaDescription: $article->metaDescription ?: null,
            );
        }

        $category = $context->output('category');
        $newCategory = is_string($category['new_category'] ?? null) && $category['new_category'] !== '' ? $category['new_category'] : null;
        $data = fn (?int $categoryId): SaveBlogPostData => new SaveBlogPostData(
            postId: null,
            translations: $translations,
            categoryId: $categoryId,
            featuredImageId: $images[0] ?? null,
            relatedPostIds: array_values(array_filter($writing['related_post_ids'] ?? [], is_int(...))),
            sources: $this->sources($context),
            origin: BlogPostOrigin::Ai,
            generationId: $context->generationId,
        );

        $created = null;

        try {
            $postId = DB::transaction(function () use ($context, $category, $newCategory, $data, &$created): int {
                $categoryId = is_int($category['category_id'] ?? null) ? $category['category_id'] : null;

                if ($categoryId === null && $newCategory !== null) {
                    $categoryId = $this->categories->idNamed($newCategory);

                    if ($categoryId === null) {
                        $category = $this->createCategory($newCategory, $context);
                        $categoryId = $category->id;
                        $created = $category->translationFor('fr')->name ?? $newCategory;
                    }
                }

                return $this->savePost->execute($data($categoryId))->id;
            });
        } catch (Throwable $e) {
            // Deux passages en meme temps : l'autre a cree le brouillon, on le reprend.
            $postId = $this->drafts->postIdForGeneration($context->generationId) ?? throw $e;
            $created = null;
        }

        return $this->ready($postId, $context, $created);
    }

    /** La categorie nouvelle proposee par CategoryStep, avec ses noms traduits par les etapes de traduction. */
    private function createCategory(string $name, GenerationStepContext $context): BlogCategory
    {
        $names = ['en' => $name];

        foreach (['fr' => 'translation_fr', 'es' => 'translation_es'] as $locale => $step) {
            $translated = $context->output($step)['category_name'] ?? null;
            $names[$locale] = is_string($translated) && trim($translated) !== '' ? $translated : $name;
        }

        return $this->saveCategory->execute(new SaveBlogCategoryData(
            categoryId: null,
            translations: array_map(static fn (string $text): BlogCategoryTranslationData => new BlogCategoryTranslationData(name: $text), $names),
            generationId: $context->generationId,
        ));
    }

    private function ready(int $postId, GenerationStepContext $context, ?string $newCategory = null): GenerationStepResult
    {
        $hasMainImage = collect($context->output('images')['images'] ?? [])->contains(fn (array $image): bool => (int) $image['number'] === 0);
        $summary = __('Le brouillon est prêt à relire, dans les trois langues.');

        if ($newCategory !== null) {
            $summary .= ' '.__('Nouvelle catégorie « :name » créée pour cet article.', ['name' => $newCategory]);
        }

        return GenerationStepResult::continue(
            ['post_id' => $postId, 'new_category' => $newCategory],
            summary: $hasMainImage ? $summary : $summary.' '.__('Il lui manque une image principale, à ajouter avant de publier.'),
            findings: new GenerationFindings(postId: $postId),
        );
    }

    /** @return list<int> */
    private function citedPostIds(string $markdown): array
    {
        return array_values(array_unique(array_map(
            static fn (string $token): int => (int) substr($token, strlen('festilaw:post/')),
            array_filter(EditorialMarkdown::linkTokens($markdown), static fn (string $token): bool => str_starts_with($token, 'festilaw:post/')),
        )));
    }

    /** @return list<BlogSourceData> les sources du dossier que l'article cite, dans l'ordre du dossier */
    private function sources(GenerationStepContext $context): array
    {
        $cited = $context->output('writing')['source_ids'] ?? [];

        return array_values(array_map(
            static fn (array $source): BlogSourceData => new BlogSourceData(
                label: trim((string) ($source['title'] ?? '')) ?: (string) ($source['publisher'] ?? $source['url']),
                url: (string) $source['url'],
            ),
            array_filter($context->output('research')['sources'] ?? [], static fn (array $source): bool => in_array($source['id'] ?? null, $cited, true)),
        ));
    }
}
