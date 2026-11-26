<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use Carbon\CarbonImmutable;

/**
 * Une generation dans une liste : l'historique, ou les dernieres de la vue d'ensemble. Ce qu'elle a consomme
 * etape par etape (modeles, tokens) se lit sur son propre ecran.
 */
final readonly class BlogGenerationJournalRow
{
    /**
     * @param  string|null  $outcome  pourquoi il n'y a pas d'article, avec l'etape en cause (nul sinon)
     * @param  int|null  $sourcePostId  l'article depuis lequel une demande d'image ou de traduction a ete faite (24/11/2026)
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $createdAt,
        public BlogGenerationType $type,
        public BlogGenerationTrigger $trigger,
        public BlogGenerationStatus $status,
        public ?string $outcome,
        public ?string $subject,
        public ?int $postId,
        public string $cost,
        public ?string $duration,
        public ?int $sourcePostId = null,
    ) {}

    /** En echec, elle se relance ; arretee a la main, elle se reprend (demande de Micha du 26/11). */
    public function isRetryable(): bool
    {
        return $this->status->isRelaunchable();
    }

    /** Le mot du bouton qui la fait repartir. */
    public function relaunchLabel(): string
    {
        return $this->status === BlogGenerationStatus::Cancelled ? __('Reprendre') : __('Relancer');
    }

    public function isCancellable(): bool
    {
        return ! $this->status->isTerminal();
    }
}
