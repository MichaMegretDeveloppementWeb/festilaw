<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationType;

/**
 * Ce qu'une etape recoit pour travailler : la demande (sujet et consignes eventuels), les reglages en
 * vigueur, et ce que les etapes deja terminees ont produit.
 */
final readonly class GenerationStepContext
{
    /**
     * @param  array<string, array<string, mixed>>  $outputs  resultat de chaque etape terminee, par cle d'etape
     * @param  array<string, mixed>  $payload  donnees propres a la demande (par exemple l'article a traduire)
     */
    public function __construct(
        public int $generationId,
        public BlogGenerationType $type,
        public ?string $topicHint,
        public ?string $instructions,
        public BlogAiSettings $settings,
        public array $outputs,
        public array $payload,
        public int $attempt,
    ) {}

    /**
     * Le resultat d'une etape precedente.
     *
     * @return array<string, mixed>
     */
    public function output(string $step): array
    {
        return $this->outputs[$step] ?? [];
    }
}
