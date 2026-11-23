<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;

/**
 * Contenu d'article refuse : ce n'est pas un document, ou il depasse les plafonds qui protegent le
 * serveur. (Un element inconnu, lui, est simplement retire : ce n'est pas une erreur.)
 */
final class BlogContentException extends BaseAppException
{
    public static function notADocument(): self
    {
        return new self(
            technicalMessage: 'Blog content is not a structured document (expected a root node of type "doc").',
            userMessage: 'Le contenu de l\'article est illisible. Rechargez la page et réessayez.',
        );
    }

    public static function tooLarge(int $bytes, int $maxBytes): self
    {
        return (new self(
            technicalMessage: "Blog content is too large: {$bytes} bytes (maximum {$maxBytes}).",
            userMessage: 'Le contenu de l\'article est trop long pour être enregistré. Raccourcissez-le ou découpez-le en plusieurs articles.',
        ))->asRefusal();
    }

    public static function tooManyNodes(int $maxNodes): self
    {
        return (new self(
            technicalMessage: "Blog content has more than {$maxNodes} nodes.",
            userMessage: 'Le contenu de l\'article comporte trop d\'éléments pour être enregistré. Simplifiez sa mise en forme.',
        ))->asRefusal();
    }

    public static function tooDeep(int $maxDepth): self
    {
        return (new self(
            technicalMessage: "Blog content is nested deeper than {$maxDepth} levels.",
            userMessage: 'Le contenu de l\'article comporte des listes imbriquées trop profondément. Simplifiez-les.',
        ))->asRefusal();
    }
}
