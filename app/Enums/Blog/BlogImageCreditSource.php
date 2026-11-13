<?php

declare(strict_types=1);

namespace App\Enums\Blog;

/**
 * Provenance d'une image du blog. Colonne sous contrainte CHECK en production (cf. BlogPostStatus).
 */
enum BlogImageCreditSource: string
{
    case Upload = 'upload';
    case Stock = 'stock';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::Upload => __('Envoyée'),
            self::Stock => __('Banque d\'images'),
            self::Ai => __('Générée par IA'),
        };
    }
}
