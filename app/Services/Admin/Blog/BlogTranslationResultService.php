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
     * La derniere traduction d'un article que le formulaire n'a pas encore recue, avec sa langue visee et si elle
     * est finie, ou nulle.
     *
     * @return array{generation_id: int, locale: string, finished: bool}|null
     */
    public function undeliveredForPost(int $postId): ?array
    {
        $row = $this->requests->undeliveredForPost($postId);
        $payload = is_string($row?->payload) ? json_decode($row->payload, true) : null;
        $locale = is_array($payload) ? (string) ($payload['translation']['to'] ?? '') : '';

        return $row === null || $locale === '' ? null : [
            'generation_id' => (int) $row->id,
            'locale' => $locale,
            'finished' => BlogGenerationStatus::tryFrom((string) $row->status)?->isTerminal() ?? true,
        ];
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
            is_string($payload['translation']['field'] ?? null) ? $payload['translation']['field'] : null,
        );
    }
}
