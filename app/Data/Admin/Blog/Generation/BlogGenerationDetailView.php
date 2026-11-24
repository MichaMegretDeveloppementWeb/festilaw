<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;

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
     * @param  string|null  $length  la longueur demandee du texte (nulle pour une generation anterieure a ce choix)
     * @param  string|null  $effort  le niveau d'effort demande (nul pour une generation anterieure a ce choix)
     * @param  string|null  $announced  le cout annonce au lancement (nul pour une generation anterieure a cette annonce)
     * @param  list<array{key: string, label: string, text: string}>  $report  compte rendu de l'IA, point par point (`key` : l'etape qui l'a ecrit)
     * @param  list<BlogGenerationStepView>  $steps  dans l'ordre du pipeline
     * @param  list<array{where: string|null, statement: string, check: string}>  $attentionPoints  affirmations gardees sur lesquelles la verification a un doute, situees dans le brouillon francais (generation terminee)
     * @param  int|null  $sourcePostId  l'article depuis lequel une demande d'image ou de traduction a ete faite (24/11/2026)
     * @param  array{url: ?string, alt: string, origin: string}|null  $obtainedImage  l'image obtenue par une demande d'image terminee
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
        public ?string $announced,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $images,
        public ?string $length,
        public ?string $effort,
        public ?int $postId,
        public array $report,
        public array $steps,
        public array $attentionPoints = [],
        public BlogGenerationType $type = BlogGenerationType::Article,
        public ?int $sourcePostId = null,
        public ?array $obtainedImage = null,
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
