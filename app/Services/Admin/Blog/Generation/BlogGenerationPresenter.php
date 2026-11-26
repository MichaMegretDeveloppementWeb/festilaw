<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Data\Blog\Generation\GenerationCostForecast;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationSafetyStop;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\MonthlySpendLevel;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use Carbon\CarbonImmutable;

/**
 * Mise en mots de ce que seul le back-office affiche d'une generation : reglage de modele, rythme, duree,
 * date, images, longueur, cout estime, depense du mois, sujet. Les mots partages avec les e-mails (etape, issue) sont dans
 * BlogGenerationLabels. Calcul pur, aucune requete.
 */
final readonly class BlogGenerationPresenter
{
    /**
     * @param  string  $scheduleTimezone  fuseau du calendrier de la generation automatique (blog.ai.schedule_timezone)
     */
    public function __construct(
        private BlogAiCostFormatter $costs,
        private GenerationSafetyStop $safetyStop,
        private string $scheduleTimezone,
    ) {}

    /**
     * La fourchette de cout d'un article telle que les ecrans l'annoncent, et le montant de son arret de securite.
     *
     * @return array{low: string, high: string, stop: string}
     */
    public function forecast(GenerationCostForecast $forecast): array
    {
        return [
            'low' => $this->costs->dollars($forecast->lowMicrousd),
            'high' => $this->costs->dollars($forecast->highMicrousd),
            'stop' => $this->costs->dollars($this->safetyStop->ceiling($forecast->highMicrousd)),
        ];
    }

    /** Le cout annonce au lancement d'une generation, ou nul pour une generation anterieure a cette annonce. */
    public function announced(?int $lowMicrousd, ?int $highMicrousd): ?string
    {
        if ($lowMicrousd === null || $highMicrousd === null) {
            return null;
        }

        return __('Annoncé entre :low et :high', ['low' => $this->costs->dollars($lowMicrousd), 'high' => $this->costs->dollars($highMicrousd)]);
    }

    /**
     * L'avertissement du plafond du mois, le meme sur le tableau de bord, les reglages et la fenetre de
     * lancement. Ce plafond ne bloque rien : le texte le dit, pour qu'on ne croie pas la generation arretee.
     *
     * @return array{level: MonthlySpendLevel, title: string, text: string}|null
     */
    public function monthlyWarning(MonthlySpendLevel $level, int $spentMicrousd, int $capMicrousd): ?array
    {
        $spent = $this->costs->monthDollars($spentMicrousd);
        $cap = $this->costs->monthDollars($capMicrousd);

        return match ($level) {
            MonthlySpendLevel::Under => null,
            MonthlySpendLevel::Near => [
                'level' => $level,
                'title' => __('La dépense du mois approche du plafond'),
                'text' => __('La génération a dépensé :spent ce mois-ci, soit :percent % du plafond de :cap. Dépasser le plafond ne bloque pas la génération.', [
                    'spent' => $spent,
                    'cap' => $cap,
                    'percent' => intdiv(100 * $spentMicrousd, max(1, $capMicrousd)),
                ]),
            ],
            MonthlySpendLevel::Over => [
                'level' => $level,
                'title' => __('Le plafond de dépense du mois est dépassé'),
                'text' => __('La génération a dépensé :spent ce mois-ci, au-delà du plafond de :cap. Ce dépassement ne bloque pas la génération. Si cette dépense est prévue, vous pouvez relever le plafond.', [
                    'spent' => $spent,
                    'cap' => $cap,
                ]),
            ],
        };
    }

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

    /**
     * Quand partira le prochain lot automatique, dit sans approximation (decisions de Micha du 23/11 et du 26/11) :
     * « dans moins d'une heure » quand il est du, sinon le jour et l'heure exacts du creneau, a l'heure de Paris
     * (« Prochains articles mardi 2 décembre à 9 h (heure de Paris). »).
     */
    public function nextLaunch(CarbonImmutable $at, CarbonImmutable $now, int $articles): string
    {
        $when = $at->getTimestamp() - $now->getTimestamp() < 3_600
            ? __('dans moins d\'une heure')
            : __(':moment (heure de Paris)', ['moment' => $this->slot($at, $now)]);

        return trans_choice('{1} Prochain article :when.|[2,*] Prochains articles :when.', $articles, ['when' => $when]);
    }

    /**
     * Un creneau de la generation automatique, a l'heure de Paris : « aujourd'hui à 16 h », « demain à 9 h »,
     * « mardi 2 décembre à 9 h », l'annee en plus quand elle change.
     */
    public function slot(CarbonImmutable $at, CarbonImmutable $now): string
    {
        $local = $at->setTimezone($this->scheduleTimezone)->locale('fr');
        $today = $now->setTimezone($this->scheduleTimezone)->startOfDay();
        $hour = __(':hour h', ['hour' => $local->format('G')]);

        return match (true) {
            $local->isSameDay($today) => __('aujourd\'hui à :hour', ['hour' => $hour]),
            $local->isSameDay($today->addDay()) => __('demain à :hour', ['hour' => $hour]),
            default => __(':day à :hour', [
                'day' => trim(implode(' ', [
                    $local->isoFormat('dddd'),
                    $local->day === 1 ? __('1er') : (string) $local->day,
                    $local->isoFormat('MMMM'),
                    $local->year === $today->year ? '' : (string) $local->year,
                ])),
                'hour' => $hour,
            ]),
        };
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

    /** « 14/11/2026 à 16:45 ». L'ecran dit a cote que l'heure est en UTC. */
    public function dateTime(CarbonImmutable $date): string
    {
        return $date->format('d/m/Y à H:i');
    }

    /** D'ou viennent les images d'une generation et combien au plus : « Banque d'images, 2 au plus ». */
    public function images(GenerationImageOptions $options): string
    {
        if ($options->mode === BlogAiImageMode::None) {
            return __('Aucune image');
        }

        $source = $options->mode === BlogAiImageMode::Stock ? __('Banque d\'images') : __('Générées par IA');

        return $options->maxImages === 1
            ? __(':source, une seule', ['source' => $source])
            : __(':source, :count au plus', ['source' => $source, 'count' => $options->maxImages]);
    }

    /** « Environ 1 000 mots (6 000 caractères) ». Un mot anglais compte environ 6 caracteres, espace comprise. */
    public function length(int $words): string
    {
        return __('Environ :words mots (:characters caractères)', [
            'words' => number_format($words, 0, ',', ' '),
            'characters' => number_format($words * 6, 0, ',', ' '),
        ]);
    }

    /**
     * Les paliers de longueur pour une liste de choix : « 1 000 mots, environ 6 000 caractères ».
     *
     * @param  list<int>  $options
     * @return array<string, string>
     */
    public function lengthOptions(array $options): array
    {
        return collect($options)->mapWithKeys(static fn (int $words): array => [(string) $words => __(':words mots, environ :characters caractères', [
            'words' => number_format($words, 0, ',', "\u{00A0}"),
            'characters' => number_format($words * 6, 0, ',', "\u{00A0}"),
        ])])->all();
    }

    /** Libelle d'un nombre d'images au plus, pour les listes de choix. Ce nombre comprend l'image principale. */
    public function imageCountLabel(int $count): string
    {
        return match ($count) {
            1 => __('Une seule, l\'image principale'),
            2 => __('Deux au plus'),
            default => __(':count au plus', ['count' => $count]),
        };
    }

    /** Duree entre deux instants ; jusqu'a maintenant si la fin n'est pas connue. */
    public function elapsed(?CarbonImmutable $from, ?CarbonImmutable $to = null): ?string
    {
        if ($from === null) {
            return null;
        }

        return $this->duration(max(0, ($to ?? CarbonImmutable::now())->getTimestamp() - $from->getTimestamp()));
    }

    /**
     * Le sujet affiche : le titre francais de l'article cree, une fois la generation terminee (le back-office
     * est en francais, et le sujet de travail est en anglais, decision de Micha du 19/11) ; sinon le sujet que
     * l'IA a retenu, sinon celui qui a ete impose, sinon rien.
     */
    public function subject(?string $postTitle, ?string $topicTitle, ?string $topicHint): ?string
    {
        foreach ([$postTitle, $topicTitle, $topicHint] as $subject) {
            if (filled($subject)) {
                return $subject;
            }
        }

        return null;
    }
}
