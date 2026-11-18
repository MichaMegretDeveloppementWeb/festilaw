<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * La fourchette de cout estimee d'un article, en millioniemes de dollar. Elle est annoncee avant le lancement
 * et gardee sur la generation. Ce n'est qu'une estimation, et le cout reel peut en sortir ; seul l'arret de
 * securite, calcule sur sa borne haute (GenerationSafetyStop), est garanti, appel par appel.
 */
final readonly class GenerationCostForecast
{
    public function __construct(
        public int $lowMicrousd,
        public int $highMicrousd,
    ) {}
}
