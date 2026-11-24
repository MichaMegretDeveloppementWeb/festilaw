<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Ce que produit une generation : un article complet, la traduction assistee d'un article saisi a la main,
 * ou une image principale trouvee depuis un article (demande de Micha du 24/11/2026). Chaque type a sa liste
 * d'etapes. Colonne sous contrainte CHECK en production (cf. BlogGenerationStatus) : un cas nouveau demande
 * une migration de rafraichissement.
 */
enum BlogGenerationType: string
{
    case Article = 'article';
    case Translation = 'translation';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Article => __('Article'),
            self::Translation => __('Traduction'),
            self::Image => __('Image'),
        };
    }
}
