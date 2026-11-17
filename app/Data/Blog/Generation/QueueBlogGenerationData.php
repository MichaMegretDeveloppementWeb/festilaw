<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationType;

/**
 * Une demande de generation faite par une personne : le type, et ce qu'elle a eventuellement impose (sujet,
 * consignes, images, longueur). `payload` porte les donnees propres au type (l'article a traduire, pour une
 * traduction). `images` et `articleWords` nuls : les valeurs par defaut des reglages.
 */
final readonly class QueueBlogGenerationData
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public BlogGenerationType $type = BlogGenerationType::Article,
        public ?string $topicHint = null,
        public ?string $instructions = null,
        public ?int $requestedBy = null,
        public array $payload = [],
        public ?GenerationImageOptions $images = null,
        public ?int $articleWords = null,
    ) {}
}
