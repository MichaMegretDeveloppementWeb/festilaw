<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Un service de la generation n'a pas pu etre verifie (verification quotidienne, festilaw:blog-health-check).
 * Le message technique va au journal ; le message utilisateur n'est jamais affiche a la cliente, qui ne voit
 * qu'« un probleme technique est signale ».
 */
final class BlogHealthCheckException extends BaseAppException
{
    public static function keyRejected(int $status): self
    {
        return new self(
            technicalMessage: "OpenRouter rejected the API key (HTTP {$status}).",
            userMessage: __('Le fournisseur d\'IA refuse la clé du site.'),
        );
    }

    public static function unreachable(string $service, string $detail, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "{$service} could not be checked: {$detail}",
            userMessage: __('Un service de la génération n\'a pas pu être vérifié.'),
            previous: $previous,
        );
    }
}
