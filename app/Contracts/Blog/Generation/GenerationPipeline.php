<?php

declare(strict_types=1);

namespace App\Contracts\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationType;

/**
 * La liste ordonnee des etapes d'un type de generation. Deux implementations : les etapes factices (essais
 * du moteur sans appel a un modele) et le pipeline editorial. Le choix se fait par configuration
 * (blog.ai.pipeline), branche dans BlogGenerationServiceProvider.
 */
interface GenerationPipeline
{
    /** @return list<GenerationStep> */
    public function steps(BlogGenerationType $type): array;
}
