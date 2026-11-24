<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Blog\Generation\BlogFeaturedImageResult;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Repositories\Blog\BlogImageRequestRepository;

/**
 * Le resultat d'une image principale demandee depuis un article (24/11/2026), pour le formulaire qui l'attend :
 * en cours tant que la generation tourne, puis l'image trouvee ou creee, ou ce qui l'a empechee. Une requete.
 * Meme forme que BlogTranslationResultService.
 */
final readonly class BlogFeaturedImageResultService
{
    public function __construct(private BlogImageRequestRepository $requests) {}

    /**
     * La demande encore en cours pour un article, avec sa source (banque ou IA), ou nulle.
     *
     * @return array{generation_id: int, mode: string}|null
     */
    public function pendingForPost(int $postId): ?array
    {
        $row = $this->requests->activeForPost($postId);
        $payload = is_string($row?->payload) ? json_decode($row->payload, true) : null;

        return $row === null ? null : ['generation_id' => (int) $row->id, 'mode' => (string) ($payload['images']['mode'] ?? 'stock')];
    }

    public function find(int $generationId): BlogFeaturedImageResult
    {
        $row = $this->requests->find($generationId);
        $status = $row === null ? null : BlogGenerationStatus::tryFrom((string) $row->status);

        if ($status === null) {
            return BlogFeaturedImageResult::missing(BlogFeaturedImageResult::FAILED);
        }

        if (! $status->isTerminal()) {
            return BlogFeaturedImageResult::pending();
        }

        $output = is_string($row->output) ? json_decode($row->output, true) : null;
        $payload = is_string($row->payload) ? json_decode($row->payload, true) : null;
        $mode = (string) ($payload['images']['mode'] ?? 'stock');

        if ($status !== BlogGenerationStatus::Completed || ! is_array($output)) {
            return BlogFeaturedImageResult::missing(BlogFeaturedImageResult::FAILED, $mode);
        }

        foreach ((array) ($output['images'] ?? []) as $image) {
            if (is_array($image) && (int) ($image['number'] ?? -1) === 0 && is_numeric($image['image_id'] ?? null)) {
                return BlogFeaturedImageResult::ready((int) $image['image_id'], $mode);
            }
        }

        $cause = (string) ($output['causes'][0] ?? '');

        return BlogFeaturedImageResult::missing(match ($cause) {
            BlogFeaturedImageResult::NONE_FIT => BlogFeaturedImageResult::NONE_FIT,
            BlogFeaturedImageResult::BANK_UNAVAILABLE => BlogFeaturedImageResult::BANK_UNAVAILABLE,
            default => BlogFeaturedImageResult::FAILED,
        }, $mode);
    }
}
