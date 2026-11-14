<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use Carbon\CarbonImmutable;

/**
 * Mise en mots de ce que seul le back-office affiche d'une generation : reglage de modele, rythme, duree,
 * sujet. Les mots partages avec les e-mails (etape, issue) sont dans BlogGenerationLabels. Calcul pur, aucune
 * requete.
 */
final readonly class BlogGenerationPresenter
{
    /** Libelle d'un reglage « modele par etape » (une cle par famille d'etapes). */
    public function modelSettingLabel(string $key): string
    {
        return match ($key) {
            'topic' => __('Choix du sujet'),
            'research' => __('Recherche'),
            'writing' => __('Rédaction'),
            'verification' => __('Vérification'),
            'translation' => __('Traduction'),
            'image' => __('Image générée'),
            default => $key,
        };
    }

    /** « 1 article chaque semaine », « 2 articles toutes les 2 semaines », « 1 article tous les 3 jours ». */
    public function rhythm(int $articles, int $periodCount, BlogAiPeriodUnit $unit): string
    {
        $period = match (true) {
            $periodCount === 1 => match ($unit) {
                BlogAiPeriodUnit::Day => __('chaque jour'),
                BlogAiPeriodUnit::Week => __('chaque semaine'),
                BlogAiPeriodUnit::Month => __('chaque mois'),
            },
            default => match ($unit) {
                BlogAiPeriodUnit::Day => __('tous les :count jours', ['count' => $periodCount]),
                BlogAiPeriodUnit::Week => __('toutes les :count semaines', ['count' => $periodCount]),
                BlogAiPeriodUnit::Month => __('tous les :count mois', ['count' => $periodCount]),
            },
        };

        return trans_choice('{1} :count article :period|[2,*] :count articles :period', $articles, ['count' => $articles, 'period' => $period]);
    }

    /** « 45 s », « 2 min 05 s », « 1 h 03 min ». Nul quand la duree n'est pas connue. */
    public function duration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }

        if ($seconds < 60) {
            return __(':seconds s', ['seconds' => $seconds]);
        }

        if ($seconds < 3_600) {
            return __(':minutes min :seconds s', ['minutes' => intdiv($seconds, 60), 'seconds' => sprintf('%02d', $seconds % 60)]);
        }

        return __(':hours h :minutes min', ['hours' => intdiv($seconds, 3_600), 'minutes' => sprintf('%02d', intdiv($seconds % 3_600, 60))]);
    }

    /** Duree entre deux instants ; jusqu'a maintenant si la fin n'est pas connue. */
    public function elapsed(?CarbonImmutable $from, ?CarbonImmutable $to = null): ?string
    {
        if ($from === null) {
            return null;
        }

        return $this->duration(max(0, ($to ?? CarbonImmutable::now())->getTimestamp() - $from->getTimestamp()));
    }

    /** Le sujet affiche : celui que l'IA a retenu, sinon celui qui a ete impose, sinon rien. */
    public function subject(?string $topicTitle, ?string $topicHint): ?string
    {
        return filled($topicTitle) ? $topicTitle : (filled($topicHint) ? $topicHint : null);
    }
}
