<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * La limite de depense d'un article : le plafond regle, augmente d'une marge (`blog.ai.article_spend_tolerance_percent`).
 * Le cout d'un article n'est qu'estime, et Micha ne veut pas bloquer un article pour un ecart de quelques
 * pour cent (decision du 18/11). La meme limite vaut avant le lancement (haut de la fourchette estimee) et
 * pendant la generation (reserve de chaque appel). Sinon, un article accepte au lancement serait arrete en
 * route, apres avoir depense, sans brouillon.
 */
final readonly class ArticleSpendLimit
{
    public function __construct(public int $percent) {}

    public function limit(int $capMicrousd): int
    {
        return intdiv($capMicrousd * (100 + $this->percent), 100);
    }
}
