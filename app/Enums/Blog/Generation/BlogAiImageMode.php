<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * D'ou viennent les images d'un article genere (reglage du back-office, pas une colonne) : une banque
 * d'images, un modele de generation d'image, ou aucune image.
 */
enum BlogAiImageMode: string
{
    case Stock = 'stock';
    case Generated = 'generated';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Stock => __('Banque d\'images'),
            self::Generated => __('Image générée par IA'),
            self::None => __('Aucune image'),
        };
    }
}
