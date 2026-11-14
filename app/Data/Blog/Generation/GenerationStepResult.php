<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;

/**
 * Ce qu'une etape rend : ce qu'elle a produit, ce qu'elle a consomme, et si la generation continue. Une
 * etape peut arreter la generation sans que ce soit une erreur (aucun sujet pertinent, sujet deja traite) :
 * la generation est alors « abandonnee » avec cette raison, et rien n'est reessaye.
 */
final readonly class GenerationStepResult
{
    /** @param  array<string, mixed>  $output */
    private function __construct(
        public array $output,
        public GenerationUsage $usage,
        public ?BlogGenerationOutcomeReason $stopReason,
    ) {}

    /** @param  array<string, mixed>  $output */
    public static function continue(array $output = [], ?GenerationUsage $usage = null): self
    {
        return new self($output, $usage ?? GenerationUsage::none(), null);
    }

    /** @param  array<string, mixed>  $output */
    public static function stop(BlogGenerationOutcomeReason $reason, array $output = [], ?GenerationUsage $usage = null): self
    {
        return new self($output, $usage ?? GenerationUsage::none(), $reason);
    }

    public function stopsGeneration(): bool
    {
        return $this->stopReason !== null;
    }
}
