<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * Ce que la verification du matin a constate de la cle OpenRouter des reglages. Une panne d'OpenRouter pendant
 * la verification ne dit rien de la cle : pas de verdict.
 */
enum OpenRouterKeyCheck: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Missing = 'missing';
}
