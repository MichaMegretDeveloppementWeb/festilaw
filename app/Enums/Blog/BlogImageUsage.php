<?php

declare(strict_types=1);

namespace App\Enums\Blog;

/**
 * Usage prevu d'une image au moment de son envoi. Non stocke : il dit seulement quelles variantes
 * produire et quelle taille minimale exiger.
 */
enum BlogImageUsage: string
{
    case Featured = 'featured';
    case Share = 'share';
    case Content = 'content';

    /** L'image principale et l'image de partage recoivent le JPEG 1200 x 630 lu par les reseaux sociaux. */
    public function needsShareVariant(): bool
    {
        return match ($this) {
            self::Featured, self::Share => true,
            self::Content => false,
        };
    }
}
