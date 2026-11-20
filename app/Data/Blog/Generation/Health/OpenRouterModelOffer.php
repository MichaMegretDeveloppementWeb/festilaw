<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Health;

/**
 * Ce qu'OpenRouter propose aujourd'hui pour un modele choisi : le fournisseur le moins cher parmi ceux qui
 * acceptent la sortie structuree et la reflexion (le site les demande toutes deux, et OpenRouter ne route
 * alors que vers eux), ses prix en cents de dollar par million de jetons, et ce que le modele sait lire.
 */
final readonly class OpenRouterModelOffer
{
    public function __construct(
        public string $modelId,
        public bool $hasEligibleProvider,
        public ?float $inputCentsPerMillion,
        public ?float $outputCentsPerMillion,
        public bool $acceptsImages,
    ) {}
}
