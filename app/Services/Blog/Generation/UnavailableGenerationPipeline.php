<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogGenerationException;

/**
 * Pipeline indisponible : la configuration ne permet pas de generer (etapes factices en production, nom de
 * pipeline inconnu). Toute demande d'etapes leve l'exception qui dit pourquoi : un lancement est refuse avec
 * son message, au lieu de faire planter le site au demarrage.
 */
final readonly class UnavailableGenerationPipeline implements GenerationPipeline
{
    public function __construct(private BlogGenerationException $reason) {}

    public function steps(BlogGenerationType $type): array
    {
        throw $this->reason;
    }
}
