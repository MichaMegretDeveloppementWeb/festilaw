<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * La fourchette de cout estimee d'un article, en millioniemes de dollar. La borne haute decide : un article
 * dont elle depasse le plafond par article ne part pas (decision de Micha du 17/11, sans tolerance).
 */
final readonly class GenerationCostForecast
{
    public function __construct(
        public int $lowMicrousd,
        public int $highMicrousd,
    ) {}

    public function exceeds(int $capMicrousd): bool
    {
        return $this->highMicrousd > $capMicrousd;
    }
}
