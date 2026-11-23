<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Une cle OpenRouter collee dans les reglages n'a pas ete enregistree : OpenRouter la refuse (refus d'utilisation,
 * la personne recolle sa cle), ou n'a pas pu la verifier (panne, au rapport du matin). L'ancienne cle reste.
 */
final class OpenRouterKeyException extends BaseAppException
{
    public static function rejected(Throwable $previous): self
    {
        return (new self(
            technicalMessage: 'OpenRouter rejected the key pasted in the generation settings.',
            userMessage: __('OpenRouter refuse cette clé. Vérifiez que vous l\'avez copiée en entier.'),
            previous: $previous,
        ))->asRefusal();
    }

    public static function unverifiable(Throwable $previous): self
    {
        return new self(
            technicalMessage: 'The key pasted in the generation settings could not be checked with OpenRouter.',
            userMessage: __('OpenRouter ne répond pas. La clé n\'a pas été changée. Réessayez dans quelques minutes.'),
            previous: $previous,
        );
    }
}
