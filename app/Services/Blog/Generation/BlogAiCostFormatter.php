<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

/**
 * Affichage d'un cout d'IA. Les couts sont enregistres en millioniemes de dollar (un appel coute souvent
 * moins d'un centime) et affiches en dollars, la monnaie de facturation du fournisseur. Calcul pur.
 */
final readonly class BlogAiCostFormatter
{
    /** « 0,26 $ » ; quatre decimales sous le centime (« 0,0034 $ ») pour ne pas afficher zero a tort. */
    public function dollars(int $microusd): string
    {
        $decimals = $microusd > 0 && $microusd < 10_000 ? 4 : 2;

        return number_format($microusd / 1_000_000, $decimals, ',', ' ').' $';
    }
}
