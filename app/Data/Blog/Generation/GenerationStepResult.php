<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;

/**
 * Ce qu'une etape rend : ce qu'elle a produit, ce qu'elle a consomme, et si la generation continue. Une
 * etape peut arreter la generation sans que ce soit une erreur (aucun sujet pertinent, sujet deja traite) :
 * la generation est alors « abandonnee » avec cette raison, et rien n'est reessaye.
 *
 * `summary` : une ou deux phrases en francais pour la personne qui relit (pourquoi ce sujet, ce qui a ete
 * consulte, comment l'article a ete traite). Affichees sur l'ecran de la generation, jamais techniques.
 *
 * `findings` : ce que la generation garde sur sa ligne (sujet, brouillon cree), recopie par le moteur.
 */
final readonly class GenerationStepResult
{
    /** @param  array<string, mixed>  $output */
    private function __construct(
        public array $output,
        public GenerationUsage $usage,
        public ?BlogGenerationOutcomeReason $stopReason,
        public ?string $summary = null,
        public ?GenerationFindings $findings = null,
    ) {}

    /** @param  array<string, mixed>  $output */
    public static function continue(array $output = [], ?GenerationUsage $usage = null, ?string $summary = null, ?GenerationFindings $findings = null): self
    {
        return new self($output, $usage ?? GenerationUsage::none(), null, $summary, $findings);
    }

    /** @param  array<string, mixed>  $output */
    public static function stop(BlogGenerationOutcomeReason $reason, array $output = [], ?GenerationUsage $usage = null, ?string $summary = null, ?GenerationFindings $findings = null): self
    {
        return new self($output, $usage ?? GenerationUsage::none(), $reason, $summary, $findings);
    }

    public function stopsGeneration(): bool
    {
        return $this->stopReason !== null;
    }
}
