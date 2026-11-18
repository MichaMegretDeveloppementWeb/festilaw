<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Editorial;

use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\BlogSourceData;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationFindings;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\SaveBlogPostData;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Exceptions\Blog\BlogEditorialException;
use App\Repositories\Blog\BlogGeneratedDraftRepository;
use App\Repositories\Blog\BlogTopicHistoryRepository;
use App\Services\Blog\Generation\Editorial\BlogInternalLinkResolver;
use App\Services\Blog\Generation\Editorial\BlogTopicSimilarity;
use App\Services\Blog\Generation\Editorial\EditorialDocumentBuilder;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;
use Throwable;

/**
 * Etape 11 · l'assemblage du brouillon (tache 6.11). Les trois versions passent par le meme chemin qu'un
 * article ecrit au back-office (`SaveBlogPostAction`) : jetons de liens traduits dans chaque langue,
 * Markdown converti en document, images placees a leurs emplacements, slugs produits depuis les titres,
 * sources du dossier, categorie, articles associes, image principale. Le brouillon est marque IA et lie a sa
 * generation.
 *
 * Rejouable : si la generation a deja son brouillon (etape rejouee apres un verrou perdu ou une erreur
 * survenue apres l'enregistrement), il est rendu tel quel, sans second brouillon. Un second controle des
 * doublons porte sur le titre final, sauf pour un sujet impose, toujours traite (decision du 18/11).
 */
final readonly class AssembleGeneratedBlogDraftAction implements GenerationStep
{
    private const LOCALES = ['en' => 'style', 'fr' => 'translation_fr', 'es' => 'translation_es'];

    public function __construct(
        private SaveBlogPostAction $savePost,
        private BlogGeneratedDraftRepository $drafts,
        private BlogTopicHistoryRepository $history,
        private BlogTopicSimilarity $similarity,
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

        $duplicateOf = filled($context->topicHint) ? null : $this->similarity->duplicateOf($articles['en']->title, $this->history->load(exceptGenerationId: $context->generationId)->titles());

        if ($duplicateOf !== null) {
            return GenerationStepResult::stop(
                BlogGenerationOutcomeReason::DuplicateTopic,
                ['duplicate_of' => $duplicateOf],
                summary: __('L\'article terminé reprenait « :title », déjà traité ou refusé, donc aucun brouillon n\'a été créé.', ['title' => $duplicateOf]),
            );
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

        $data = new SaveBlogPostData(
            postId: null,
            translations: $translations,
            categoryId: is_int($writing['category_id'] ?? null) ? $writing['category_id'] : null,
            featuredImageId: $images[0] ?? null,
            relatedPostIds: array_values(array_filter($writing['related_post_ids'] ?? [], is_int(...))),
            sources: $this->sources($context),
            origin: BlogPostOrigin::Ai,
            generationId: $context->generationId,
        );

        try {
            $postId = $this->savePost->execute($data)->id;
        } catch (Throwable $e) {
            // Deux passages en meme temps : l'autre a cree le brouillon, on le reprend.
            $postId = $this->drafts->postIdForGeneration($context->generationId) ?? throw $e;
        }

        return $this->ready($postId, $context);
    }

    private function ready(int $postId, GenerationStepContext $context): GenerationStepResult
    {
        $hasMainImage = collect($context->output('images')['images'] ?? [])->contains(fn (array $image): bool => (int) $image['number'] === 0);
        $summary = __('Le brouillon est prêt à relire, dans les trois langues.');

        return GenerationStepResult::continue(
            ['post_id' => $postId],
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
