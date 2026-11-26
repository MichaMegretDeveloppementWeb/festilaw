<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogEffortLevel;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reglages de la generation par IA, tels que le back-office les enregistre (cle `blog.ai` de la table
 * settings). Toujours complet et coherent : ce qui manque ou sort des bornes est remplace par la valeur de
 * depart ou ramene dans les bornes par `fromStored()`.
 *
 * Les sujets a privilegier ou a eviter et la mention de transparence ont ete retires le 19/11 (decision de
 * Micha) : un sujet se donne au lancement, et la mention n'est pas obligatoire. D'anciens reglages qui les
 * portent encore se lisent sans eux, et les perdent au prochain enregistrement.
 *
 * Le calendrier de la generation automatique (decision de Micha du 26/11) : un lot par periode, le jour choisi
 * (jour de la semaine ou du mois selon l'unite) a l'heure choisie, comptes a partir de `startsAt`, le moment ou
 * ce calendrier a ete pose (BlogGenerationSlots).
 */
final readonly class BlogAiSettings
{
    public const MAX_IMAGES = 3;

    /** Le jour du mois s'arrete au 28 : il existe chaque mois. */
    public const LAST_MONTH_DAY = 28;

    /**
     * @param  array<string, string>  $models  identifiant de modele par etape (sujet, recherche, redaction...)
     */
    public function __construct(
        public bool $enabled,
        public int $periodCount,
        public BlogAiPeriodUnit $periodUnit,
        public int $articlesPerRun,
        public int $pendingDraftsCap,
        public int $monthlySpendCapCents,
        public array $models,
        public BlogAiImageMode $imageMode,
        public int $maxImages,
        public string $notificationEmail,
        public int $articleWords,
        public BlogEffortLevel $effort,
        public int $weekday,
        public int $monthDay,
        public int $hour,
        public ?CarbonImmutable $startsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $stored  ce qui est enregistre (peut etre vide ou partiel)
     * @param  array<string, mixed>  $defaults  valeurs de depart (config/blog.php, section ai.defaults)
     */
    public static function fromStored(array $stored, array $defaults, string $fallbackEmail, ArticleLengthRules $length): self
    {
        $value = static fn (string $key): mixed => $stored[$key] ?? $defaults[$key] ?? null;

        $models = [];
        foreach ((array) ($defaults['models'] ?? []) as $step => $default) {
            $chosen = trim((string) ($stored['models'][$step] ?? ''));
            $models[(string) $step] = $chosen !== '' ? $chosen : (string) $default;
        }

        $email = trim((string) $value('notification_email'));

        return new self(
            enabled: (bool) $value('enabled'),
            periodCount: max(1, (int) $value('period_count')),
            periodUnit: BlogAiPeriodUnit::tryFrom((string) $value('period_unit')) ?? BlogAiPeriodUnit::Week,
            articlesPerRun: max(1, (int) $value('articles_per_run')),
            pendingDraftsCap: max(1, (int) $value('pending_drafts_cap')),
            monthlySpendCapCents: max(0, (int) $value('monthly_spend_cap_cents')),
            models: $models,
            imageMode: BlogAiImageMode::tryFrom((string) $value('image_mode')) ?? BlogAiImageMode::Stock,
            maxImages: min(self::MAX_IMAGES, max(1, (int) $value('max_images'))),
            notificationEmail: $email !== '' ? $email : $fallbackEmail,
            articleWords: $length->clamp((int) $value('article_words')),
            effort: BlogEffortLevel::tryFrom((string) $value('effort')) ?? BlogEffortLevel::Medium,
            weekday: min(7, max(1, (int) $value('weekday'))),
            monthDay: min(self::LAST_MONTH_DAY, max(1, (int) $value('month_day'))),
            hour: min(23, max(0, (int) $value('hour'))),
            startsAt: self::moment($stored['starts_at'] ?? null),
        );
    }

    /**
     * Les memes reglages, avec le point de depart du calendrier decide par rapport aux reglages en place : il reste
     * celui d'avant si la generation automatique etait deja activee au meme rythme, au meme jour et a la meme heure,
     * et repart de maintenant sinon. Changer le nombre d'articles, la longueur ou les modeles ne decale donc pas les
     * lots ; changer le jour ou l'heure fait partir le prochain lot au premier nouveau creneau.
     */
    public function withCalendarFrom(self $previous, CarbonImmutable $now): self
    {
        return new self(...[...get_object_vars($this), 'startsAt' => $this->keepsCalendarOf($previous) ? $previous->startsAt : $now]);
    }

    /** Vrai quand ces reglages gardent le calendrier en place : meme rythme, meme jour, meme heure, deja actif. */
    public function keepsCalendarOf(self $previous): bool
    {
        return $previous->enabled && $previous->startsAt !== null && $this->calendar() === $previous->calendar();
    }

    /**
     * Les memes reglages avec une autre generation automatique : sert a l'apercu du prochain lot dans les reglages,
     * sur les valeurs a l'ecran, avant l'enregistrement.
     */
    public function withAutomatic(bool $enabled, int $periodCount, BlogAiPeriodUnit $periodUnit, int $weekday, int $monthDay, int $hour): self
    {
        return new self(...[
            ...get_object_vars($this),
            'enabled' => $enabled,
            'periodCount' => $periodCount,
            'periodUnit' => $periodUnit,
            'weekday' => $weekday,
            'monthDay' => $monthDay,
            'hour' => $hour,
        ]);
    }

    /**
     * Ce qui fixe les creneaux : le jour de la semaine ne compte qu'en semaines, le jour du mois qu'en mois.
     *
     * @return array{int, string, ?int, ?int, int}
     */
    private function calendar(): array
    {
        return [
            $this->periodCount,
            $this->periodUnit->value,
            $this->periodUnit === BlogAiPeriodUnit::Week ? $this->weekday : null,
            $this->periodUnit === BlogAiPeriodUnit::Month ? $this->monthDay : null,
            $this->hour,
        ];
    }

    private static function moment(mixed $stored): ?CarbonImmutable
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($stored)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** Plafond de depense du mois, dans l'unite des couts enregistres (millioniemes de dollar). */
    public function monthlySpendCapMicrousd(): int
    {
        return $this->monthlySpendCapCents * 10_000;
    }

    public function modelFor(string $step): ?string
    {
        return $this->models[$step] ?? null;
    }

    /**
     * Les memes reglages avec d'autres modeles pour certaines etapes (banc d'essai des modeles).
     *
     * @param  array<string, string>  $models  modele par reglage, a la place de celui des reglages
     */
    public function withModels(array $models): self
    {
        return new self(...[...get_object_vars($this), 'models' => [...$this->models, ...$models]]);
    }

    /**
     * La forme enregistree.
     *
     * @return array<string, mixed>
     */
    public function toStored(): array
    {
        return [
            'enabled' => $this->enabled,
            'period_count' => $this->periodCount,
            'period_unit' => $this->periodUnit->value,
            'articles_per_run' => $this->articlesPerRun,
            'pending_drafts_cap' => $this->pendingDraftsCap,
            'monthly_spend_cap_cents' => $this->monthlySpendCapCents,
            'models' => $this->models,
            'image_mode' => $this->imageMode->value,
            'max_images' => $this->maxImages,
            'notification_email' => $this->notificationEmail,
            'article_words' => $this->articleWords,
            'effort' => $this->effort->value,
            'weekday' => $this->weekday,
            'month_day' => $this->monthDay,
            'hour' => $this->hour,
            'starts_at' => $this->startsAt?->utc()->toIso8601String(),
        ];
    }
}
