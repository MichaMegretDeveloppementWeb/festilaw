<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogPost;
use App\Repositories\Blog\BlogGenerationSpendRepository;
use Carbon\CarbonImmutable;

/**
 * Les deux plafonds de la generation : la depense du mois civil (UTC), et le nombre de brouillons generes
 * qui attendent encore une relecture. Ils se verifient au lancement ; une generation commencee va au bout.
 */
final readonly class BlogGenerationBudget
{
    public function __construct(private BlogGenerationSpendRepository $spend) {}

    /** Depense du mois en cours, en millioniemes de dollar. */
    public function spentThisMonthMicrousd(): int
    {
        $start = CarbonImmutable::now()->startOfMonth();

        return $this->spend->spentMicrousdBetween($start, $start->addMonth());
    }

    public function spendCapReached(BlogAiSettings $settings): bool
    {
        return $this->spentThisMonthMicrousd() >= $settings->monthlySpendCapMicrousd();
    }

    /** Articles d'origine IA encore en brouillon : ceux que personne n'a encore publies ni supprimes. */
    public function pendingDrafts(): int
    {
        return BlogPost::query()
            ->where('origin', BlogPostOrigin::Ai->value)
            ->where('status', BlogPostStatus::Draft->value)
            ->count();
    }

    /** Combien de generations la tache planifiee peut encore lancer avant d'atteindre le plafond de brouillons. */
    public function remainingDraftRoom(BlogAiSettings $settings): int
    {
        return max(0, $settings->pendingDraftsCap - $this->pendingDrafts());
    }
}
