<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

/**
 * Affichage d'un cout d'IA. Les couts sont enregistres en millioniemes de dollar (un appel coute souvent
 * moins d'un centime) et affiches en dollars, la monnaie de facturation du fournisseur. Calcul pur.
 */
final readonly class BlogAiCostFormatter
{
    /**
     * Toujours quatre decimales, au centieme de centime pres (decision de Micha du 2026-11-17) :
     * « 0,0125 $ », « 0,1000 $ ». Deux decimales cachaient l'essentiel d'un cout d'etape, et le total ne
     * correspondait plus a la somme des etapes affichees. Les espaces sont insecables : dans un bloc etroit,
     * « 0,2200 $ » se coupait avant le symbole (decision de Micha du 18/11).
     */
    public function dollars(int $microusd): string
    {
        return number_format($microusd / 1_000_000, 4, ',', "\u{00A0}")."\u{00A0}$";
    }
}
