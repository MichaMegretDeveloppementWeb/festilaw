<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use Carbon\CarbonImmutable;

/**
 * Une generation et ses etapes : la carte de suivi en direct et l'ecran de detail affichent la meme chose.
 * Aucun message technique : `outcome` dit ce qui s'est passe (et a quelle etape), `advice` ce que la personne
 * peut faire.
 */
final readonly class BlogGenerationDetailView
{
    /** @param  list<BlogGenerationStepView>  $steps  dans l'ordre du pipeline */
    public function __construct(
        public int $id,
        public BlogGenerationTrigger $trigger,
        public BlogGenerationStatus $status,
        public ?string $outcome,
        public ?string $advice,
        public ?string $subject,
        public ?string $topicHint,
        public ?string $instructions,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $finishedAt,
        public ?string $duration,
        public string $cost,
        public bool $cancelRequested,
        public ?int $postId,
        public array $steps,
    ) {}

    public function isActive(): bool
    {
        return ! $this->status->isTerminal();
    }

    public function isRetryable(): bool
    {
        return $this->status === BlogGenerationStatus::Failed;
    }

    public function completedSteps(): int
    {
        return count(array_filter($this->steps, static fn (BlogGenerationStepView $step): bool => $step->status === BlogGenerationStepStatus::Completed));
    }

    /** Avancement en pourcentage, d'apres le nombre d'etapes terminees. */
    public function percent(): int
    {
        return $this->steps === [] ? 0 : (int) round(100 * $this->completedSteps() / count($this->steps));
    }

    /** L'etape en cours d'execution, s'il y en a une. */
    public function runningStep(): ?BlogGenerationStepView
    {
        foreach ($this->steps as $step) {
            if ($step->status === BlogGenerationStepStatus::Running) {
                return $step;
            }
        }

        return null;
    }
}
