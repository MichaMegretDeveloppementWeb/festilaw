<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Ce que produit une generation : un article complet, ou la traduction assistee d'un article saisi a la
 * main. Chaque type a sa liste d'etapes. Colonne sous contrainte CHECK en production (cf. BlogGenerationStatus).
 */
enum BlogGenerationType: string
{
    case Article = 'article';
    case Translation = 'translation';

    public function label(): string
    {
        return match ($this) {
            self::Article => __('Article'),
            self::Translation => __('Traduction'),
        };
    }
}
