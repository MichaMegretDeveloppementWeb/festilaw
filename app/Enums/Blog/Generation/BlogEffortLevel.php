<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Niveau d'effort d'une generation (demande de Micha du 19/11) : combien de sources elle lit et combien elle
 * reflechit, donc ce qu'elle coute. Ce que chaque niveau change, etape par etape, s'ecrit dans
 * `blog.ai.effort.levels` (BlogEffortLevels) : monter d'un niveau monte a la fois la reflexion et les
 * recherches (regle de Micha du 19/11).
 */
enum BlogEffortLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Faible'),
            self::Medium => __('Moyen (recommandé)'),
            self::High => __('Élevé'),
        };
    }

    /**
     * Le niveau juste en dessous, pour reprendre une reponse coupee a sa limite avec moins de reflexion
     * (controle du 20/11), ou nul au plus bas.
     */
    public function lower(): ?self
    {
        return match ($this) {
            self::High => self::Medium,
            self::Medium => self::Low,
            self::Low => null,
        };
    }

    /** Le nom court, sur la page d'une generation : « Moyen », sans la recommandation. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Low => __('Faible'),
            self::Medium => __('Moyen'),
            self::High => __('Élevé'),
        };
    }
}
