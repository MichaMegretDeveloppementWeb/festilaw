<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Repositories\Blog\BlogGenerationLockRepository;
use App\Repositories\Blog\PendingBlogGenerationRepository;
use Carbon\CarbonImmutable;

/**
 * Un passage du worker : prend la plus ancienne generation a traiter, s'il y en a une, et la traite jusqu'a
 * son issue, ou jusqu'a l'heure de releve (elle la rend alors entre deux etapes, pour le worker suivant).
 * Rend faux quand il n'y avait rien a faire, ou quand un autre worker a pris la generation au meme instant
 * (la prise est atomique : un seul gagne).
 */
final readonly class ProcessNextBlogGenerationAction
{
    public function __construct(
        private PendingBlogGenerationRepository $pending,
        private BlogGenerationLockRepository $locks,
        private RunBlogGenerationAction $run,
        private int $claimSeconds,
    ) {}

    /** @param  CarbonImmutable|null  $handOverAt  heure a partir de laquelle rendre la generation entre deux etapes (nul : jamais) */
    public function execute(string $owner, ?CarbonImmutable $handOverAt = null): bool
    {
        $now = CarbonImmutable::now();
        $generationId = $this->pending->nextId($now);

        if ($generationId === null || ! $this->locks->claim($generationId, $owner, $now, $now->addSeconds($this->claimSeconds))) {
            return false;
        }

        $this->run->execute($generationId, $owner, $handOverAt);

        return true;
    }
}
