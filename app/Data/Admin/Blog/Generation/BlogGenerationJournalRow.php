<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use Carbon\CarbonImmutable;

/**
 * Une ligne du journal des generations.
 */
final readonly class BlogGenerationJournalRow
{
    /**
     * @param  string|null  $outcome  pourquoi il n'y a pas d'article, avec l'etape en cause (nul sinon)
     * @param  list<string>  $models  modeles utilises par les etapes
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $createdAt,
        public BlogGenerationTrigger $trigger,
        public BlogGenerationStatus $status,
        public ?string $outcome,
        public ?string $subject,
        public array $models,
        public int $inputTokens,
        public int $outputTokens,
        public string $cost,
        public ?string $duration,
    ) {}

    public function isRetryable(): bool
    {
        return $this->status === BlogGenerationStatus::Failed;
    }

    public function isCancellable(): bool
    {
        return ! $this->status->isTerminal();
    }
}
