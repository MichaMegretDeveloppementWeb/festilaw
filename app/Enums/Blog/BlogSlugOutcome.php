<?php

declare(strict_types=1);

namespace App\Enums\Blog;

/**
 * Issue de la resolution d'un slug d'article demande par un visiteur. Non stocke.
 */
enum BlogSlugOutcome: string
{
    /** Slug vivant d'un article publie : page servie (200). */
    case Found = 'found';

    /** Ancien slug d'un article publie : redirection permanente (301) vers le slug actuel. */
    case Moved = 'moved';

    /** Slug d'un article supprime : la page n'existe plus (410). */
    case Gone = 'gone';

    /** Slug inconnu, mal forme, ou article non publie (404). */
    case NotFound = 'not_found';
}
