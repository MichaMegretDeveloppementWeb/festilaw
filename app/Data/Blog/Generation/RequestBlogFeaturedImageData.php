<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiImageMode;

/**
 * Une demande d'image principale depuis le formulaire d'article (demande de Micha du 24/11/2026) : ou la chercher
 * (banque d'images ou IA), le titre et l'extrait de chaque langue tels qu'ils sont dans le formulaire, et l'image
 * actuelle quand il y en a une, pour en obtenir une autre.
 */
final readonly class RequestBlogFeaturedImageData
{
    /**
     * @param  array<string, string>  $titles  titre par langue
     * @param  array<string, string>  $excerpts  extrait par langue
     * @param  int|null  $currentImageId  l'image principale actuelle du formulaire, a remplacer par une autre
     */
    public function __construct(
        public BlogAiImageMode $mode,
        public array $titles,
        public array $excerpts,
        public ?int $currentImageId = null,
        public ?int $postId = null,
        public ?int $requestedBy = null,
    ) {}
}
