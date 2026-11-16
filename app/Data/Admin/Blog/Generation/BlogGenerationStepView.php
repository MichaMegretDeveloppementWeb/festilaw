<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;

/**
 * Une etape d'une generation telle que l'ecran l'affiche. Une etape du pipeline qui n'a pas encore commence
 * n'a pas de ligne en base : elle est « a faire », sans essai ni consommation.
 */
final readonly class BlogGenerationStepView
{
    /**
     * @param  string|null  $modelName  nom commercial du modele utilise (son identifiant s'il n'est plus dans la liste)
     * @param  string|null  $summary  compte rendu de l'etape, en une ou deux phrases
     */
    public function __construct(
        public string $key,
        public string $label,
        public BlogGenerationStepStatus $status,
        public int $attempt,
        public ?string $modelName,
        public int $inputTokens,
        public int $outputTokens,
        public int $webSearches,
        public string $cost,
        public bool $costIsEstimated,
        public ?string $duration,
        public ?string $summary,
    ) {}

    public function hasRun(): bool
    {
        return $this->attempt > 0;
    }
}
