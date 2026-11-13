<?php

declare(strict_types=1);

namespace App\Data\Blog;

use App\Enums\Blog\BlogSlugOutcome;

/**
 * Resultat de la resolution d'un slug d'article : ce que la page publique doit repondre.
 */
final readonly class BlogSlugResolution
{
    private function __construct(
        public BlogSlugOutcome $outcome,
        public ?int $postId = null,
        public ?string $currentSlug = null,
    ) {}

    public static function found(int $postId): self
    {
        return new self(BlogSlugOutcome::Found, postId: $postId);
    }

    public static function moved(int $postId, string $currentSlug): self
    {
        return new self(BlogSlugOutcome::Moved, postId: $postId, currentSlug: $currentSlug);
    }

    public static function gone(): self
    {
        return new self(BlogSlugOutcome::Gone);
    }

    public static function notFound(): self
    {
        return new self(BlogSlugOutcome::NotFound);
    }
}
