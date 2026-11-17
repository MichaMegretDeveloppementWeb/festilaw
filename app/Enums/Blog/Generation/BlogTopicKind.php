<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Les deux types d'articles du referentiel editorial (annexe A.3 du cahier des charges) : un guide durable
 * qui repond a une question precise, ou un changement recent avec ce qu'il implique pour les vendeurs.
 * Choisi par l'etape « sujet », garde sur la generation (`topic_kind`).
 */
enum BlogTopicKind: string
{
    case Evergreen = 'evergreen';
    case News = 'news';

    public function label(): string
    {
        return match ($this) {
            self::Evergreen => __('Article de fond'),
            self::News => __('Actualité'),
        };
    }
}
