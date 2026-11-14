<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationType;

/**
 * Une demande de generation faite par une personne : le type, et ce qu'elle a eventuellement impose (sujet,
 * consignes). `payload` porte les donnees propres au type (l'article a traduire, pour une traduction).
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
    ) {}
}
