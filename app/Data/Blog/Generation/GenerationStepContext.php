<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Enums\Blog\Generation\BlogGenerationType;
use Closure;

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
     * @param  BlogEffortLevel  $effort  le niveau d'effort de la generation (reflexion, recherches web)
     * @param  Closure(): bool|null  $keepLock  prolonge le verrou pendant une etape longue (images) ; faux s'il est perdu
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
        public BlogEffortLevel $effort = BlogEffortLevel::Medium,
        public ?Closure $keepLock = null,
    ) {}

    /**
     * Prolonge le verrou entre deux morceaux d'une etape longue (controle du 20/11) : sans cela, un autre
     * worker pourrait reprendre l'etape en cours et la payer deux fois. Vrai hors d'une generation.
     */
    public function keepLock(): bool
    {
        return $this->keepLock === null || ($this->keepLock)();
    }

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
