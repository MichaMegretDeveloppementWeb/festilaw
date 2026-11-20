<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Enums\Blog\Generation\MonthlySpendLevel;
use App\Models\BlogPost;
use App\Repositories\Blog\BlogGenerationSpendRepository;
use Carbon\CarbonImmutable;

/**
 * La depense du mois civil (UTC) et le nombre de brouillons generes qui attendent encore une relecture. La
 * depense du mois ne bloque rien, elle avertit (MonthlySpendLevel) ; le plafond de brouillons suspend la
 * generation automatique.
 */
final readonly class BlogGenerationBudget
{
    /** @param  int  $warningPercent  le pourcentage du plafond du mois a partir duquel on avertit */
    public function __construct(
        private BlogGenerationSpendRepository $spend,
        private int $warningPercent = 80,
    ) {}

    /** Depense du mois en cours, en millioniemes de dollar. */
    public function spentThisMonthMicrousd(): int
    {
        $start = CarbonImmutable::now()->startOfMonth();

        return $this->spend->spentMicrousdBetween($start, $start->addMonth());
    }

    /**
     * Sans requete : la depense se lit une fois par l'appelant, qui peut la comparer a un plafond pas encore
     * enregistre (celui de l'ecran des reglages).
     */
    public function monthlyLevel(int $spentMicrousd, int $capMicrousd): MonthlySpendLevel
    {
        return match (true) {
            $spentMicrousd > $capMicrousd => MonthlySpendLevel::Over,
            $capMicrousd > 0 && $spentMicrousd * 100 >= $capMicrousd * $this->warningPercent => MonthlySpendLevel::Near,
            default => MonthlySpendLevel::Under,
        };
    }

    public function warningPercent(): int
    {
        return $this->warningPercent;
    }

    /**
     * Articles d'origine IA en brouillon et jamais publies : ceux qui attendent leur premiere relecture. Un
     * article depublie redevient un brouillon mais n'en fait pas partie, sinon depublier de vieux articles
     * arreterait la generation automatique pour toujours (controle du 20/11).
     */
    public function pendingDrafts(): int
    {
        return BlogPost::query()
            ->where('origin', BlogPostOrigin::Ai->value)
            ->where('status', BlogPostStatus::Draft->value)
            ->whereNull('published_at')
            ->count();
    }

    /** Combien de generations la tache planifiee peut encore lancer avant d'atteindre le plafond de brouillons. */
    public function remainingDraftRoom(BlogAiSettings $settings): int
    {
        return max(0, $settings->pendingDraftsCap - $this->pendingDrafts());
    }
}
