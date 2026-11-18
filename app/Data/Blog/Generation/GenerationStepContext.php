<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationType;

/**
 * Ce qu'une etape recoit pour travailler : la demande (sujet et consignes eventuels), les reglages en
 * vigueur, ce que les etapes deja terminees ont produit, et ce qui reste avant l'arret de securite au
 * debut de l'essai.
 */
final readonly class GenerationStepContext
{
    /**
     * @param  array<string, array<string, mixed>>  $outputs  resultat de chaque etape terminee, par cle d'etape
     * @param  array<string, mixed>  $payload  donnees propres a la demande (par exemple l'article a traduire)
     * @param  int|null  $budgetLeftMicrousd  ce qui reste avant l'arret de securite ; nul : aucun
     * @param  GenerationLength|null  $length  la longueur demandee du texte ; nulle : aucune exigence
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
        public ?int $budgetLeftMicrousd = null,
        public ?GenerationLength $length = null,
    ) {}

    /** Ce qui reste pour un appel, une fois deduit ce que l'etape a deja depense pendant cet essai. */
    public function budgetLeftAfter(?GenerationUsage $spent): ?int
    {
        return $this->budgetLeftMicrousd === null ? null : $this->budgetLeftMicrousd - ($spent?->costMicrousd ?? 0);
    }

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
