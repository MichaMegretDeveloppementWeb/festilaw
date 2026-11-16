<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;

/**
 * Une generation et ses etapes, pour son ecran et pour la tuile « En cours » de la vue d'ensemble.
 * Aucun message technique : `outcome` dit ce qui s'est passe (l'etape en cause, ou qui a arrete la generation
 * et quand), `advice` ce que la personne peut faire.
 */
final readonly class BlogGenerationDetailView
{
    /**
     * @param  string  $launched  qui a lance la generation et quand, en une phrase
     * @param  string|null  $images  d'ou viennent les images et combien au plus (nul pour une generation anterieure a ce choix)
     * @param  list<array{key: string, label: string, text: string}>  $report  compte rendu de l'IA, point par point (`key` : l'etape qui l'a ecrit)
     * @param  list<BlogGenerationStepView>  $steps  dans l'ordre du pipeline
     */
    public function __construct(
        public int $id,
        public BlogGenerationTrigger $trigger,
        public BlogGenerationStatus $status,
        public string $launched,
        public ?string $outcome,
        public ?string $advice,
        public ?string $subject,
        public ?string $topicHint,
        public ?string $instructions,
        public ?string $duration,
        public string $cost,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $images,
        public bool $cancelRequested,
        public ?int $postId,
        public array $report,
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
