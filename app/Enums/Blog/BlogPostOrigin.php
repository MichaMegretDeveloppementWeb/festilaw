<?php

declare(strict_types=1);

namespace App\Enums\Blog;

/**
 * Origine d'un article : saisi a la main ou propose par l'IA. Colonne sous contrainte CHECK en
 * production (cf. BlogPostStatus).
 */
enum BlogPostOrigin: string
{
    case Manual = 'manual';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manuel'),
            self::Ai => __('IA'),
        };
    }
}
