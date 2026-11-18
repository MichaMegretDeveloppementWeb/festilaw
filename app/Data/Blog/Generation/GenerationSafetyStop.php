<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * L'arret de securite d'une generation : un pourcentage du haut de son cout annonce (`blog.ai.safety_stop_percent`,
 * 200, decision de Micha du 18/11). Il remplace le plafond par article, qui refusait des lancements sans proteger
 * de rien : la generation va au bout de ce qu'elle a annonce, et ne s'arrete que devant une derive grave.
 */
final readonly class GenerationSafetyStop
{
    public function __construct(public int $percent) {}

    /** Le montant au-dela duquel la generation s'arrete, pour un haut annonce donne. */
    public function ceiling(int $announcedHighMicrousd): int
    {
        return intdiv($announcedHighMicrousd * $this->percent, 100);
    }
}
