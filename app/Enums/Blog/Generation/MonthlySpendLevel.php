<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Ou en est la depense du mois par rapport a son plafond. Le plafond du mois ne bloque rien (decision de
 * Micha du 17/11) : il avertit quand on s'en approche, puis une fois depasse.
 */
enum MonthlySpendLevel: string
{
    case Under = 'under';
    case Near = 'near';
    case Over = 'over';
}
