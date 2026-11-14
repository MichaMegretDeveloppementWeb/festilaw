<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Ce qu'une etape a consomme chez le fournisseur d'IA : jetons, recherches web et cout en millioniemes de
 * dollar. `costIsEstimated` : le fournisseur n'a pas renvoye le cout reel, il vient de la table de tarifs.
 */
final readonly class GenerationUsage
{
    public function __construct(
        public ?string $model = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cachedTokens = 0,
        public int $reasoningTokens = 0,
        public int $webSearches = 0,
        public int $costMicrousd = 0,
        public bool $costIsEstimated = false,
    ) {}

    /** Une etape qui n'appelle aucun modele. */
    public static function none(): self
    {
        return new self;
    }

    /** Cumul de deux consommations (plusieurs appels dans une etape, ou plusieurs essais). */
    public function plus(self $other): self
    {
        return new self(
            model: $other->model ?? $this->model,
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cachedTokens: $this->cachedTokens + $other->cachedTokens,
            reasoningTokens: $this->reasoningTokens + $other->reasoningTokens,
            webSearches: $this->webSearches + $other->webSearches,
            costMicrousd: $this->costMicrousd + $other->costMicrousd,
            costIsEstimated: $this->costIsEstimated || $other->costIsEstimated,
        );
    }
}
