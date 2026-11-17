<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Une demande de traduction assistee depuis le formulaire d'article : la langue de depart, la langue a
 * remplir, et le texte de depart tel qu'il est dans le formulaire (y compris s'il n'est pas encore
 * enregistre).
 */
final readonly class RequestBlogTranslationData
{
    /** @param  array<string, mixed>|null  $content  le document de l'editeur dans la langue de depart */
    public function __construct(
        public string $from,
        public string $to,
        public string $title,
        public string $excerpt,
        public string $metaTitle,
        public string $metaDescription,
        public ?array $content,
        public ?int $postId = null,
        public ?int $requestedBy = null,
    ) {}
}
