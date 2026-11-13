<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

use App\Data\Blog\BlogPostReadiness;

/**
 * Issue d'un "enregistrer puis publier" ou "enregistrer puis programmer" : l'article est toujours
 * enregistre ; le changement de statut n'a lieu que s'il est pret.
 */
final readonly class BlogPostStatusChangeResult
{
    private function __construct(
        public int $postId,
        public bool $changed,
        public ?BlogPostReadiness $readiness,
    ) {}

    public static function done(int $postId): self
    {
        return new self($postId, true, null);
    }

    /** Enregistre, mais pas pret : $readiness dit ce qui bloque. */
    public static function notReady(int $postId, BlogPostReadiness $readiness): self
    {
        return new self($postId, false, $readiness);
    }
}
