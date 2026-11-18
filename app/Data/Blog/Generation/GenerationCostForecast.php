<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * La fourchette de cout estimee d'un article, en millioniemes de dollar. La borne haute decide : un article
 * dont elle depasse la limite (le plafond par article augmente de sa marge, ArticleSpendLimit) ne part pas.
 * Ce n'est qu'une estimation, et le cout reel peut en sortir. Seule la limite est garantie, appel par appel.
 */
final readonly class GenerationCostForecast
{
    public function __construct(
        public int $lowMicrousd,
        public int $highMicrousd,
    ) {}

    public function exceeds(int $limitMicrousd): bool
    {
        return $this->highMicrousd > $limitMicrousd;
    }
}
