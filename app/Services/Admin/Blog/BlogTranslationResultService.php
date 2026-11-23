<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Blog\Generation\BlogTranslationResult;
use App\Data\Blog\Generation\EditorialArticle;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Repositories\Blog\BlogTranslationRequestRepository;
use App\Services\Blog\Generation\Editorial\EditorialDocumentBuilder;

/**
 * Le resultat d'une traduction assistee, pour le formulaire qui l'attend : en cours tant que la generation
 * tourne, puis les textes de la langue visee et son document, images remises a leurs emplacements avec
 * leur largeur et leur position d'origine. Une requete.
 */
final readonly class BlogTranslationResultService
{
    public function __construct(
        private BlogTranslationRequestRepository $requests,
        private EditorialDocumentBuilder $documents,
    ) {}

    /**
     * La traduction encore en cours pour un article, avec sa langue visee, ou nulle.
     *
     * @return array{generation_id: int, locale: string}|null
     */
    public function pendingForPost(int $postId): ?array
    {
        $row = $this->requests->activeForPost($postId);
        $payload = is_string($row?->payload) ? json_decode($row->payload, true) : null;
        $locale = is_array($payload) ? (string) ($payload['translation']['to'] ?? '') : '';

        return $row === null || $locale === '' ? null : ['generation_id' => (int) $row->id, 'locale' => $locale];
    }

    public function find(int $generationId): BlogTranslationResult
    {
        $row = $this->requests->find($generationId);
        $status = $row === null ? null : BlogGenerationStatus::tryFrom((string) $row->status);

        if ($status === null) {
            return BlogTranslationResult::failed();
        }

        if (! $status->isTerminal()) {
            return BlogTranslationResult::pending();
        }

        $output = is_string($row->output) ? json_decode($row->output, true) : null;
        $payload = is_string($row->payload) ? json_decode($row->payload, true) : null;

        if ($status !== BlogGenerationStatus::Completed || ! is_array($output) || ! is_array($output['article'] ?? null)) {
            return BlogTranslationResult::failed();
        }

        $article = EditorialArticle::fromArray($output['article']);
        $images = [];

        foreach ($payload['translation']['images'] ?? [] as $number => $attributes) {
            if (is_array($attributes)) {
                $images[(int) $number] = $attributes;
            }
        }

        return BlogTranslationResult::ready(
            (string) ($output['locale'] ?? $payload['translation']['to'] ?? ''),
            ['title' => $article->title, 'excerpt' => $article->excerpt, 'metaTitle' => $article->metaTitle, 'metaDescription' => $article->metaDescription],
            $this->documents->build($article->body, $images),
        );
    }
}
