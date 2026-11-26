<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use Carbon\CarbonImmutable;

/**
 * Les creneaux de la generation automatique (decision de Micha du 26/11) : le premier creneau qui suit strictement
 * le point de depart du calendrier (`startsAt`) au jour et a l'heure choisis, puis un creneau toutes les N periodes.
 * « Chaque mardi a 9 h », « toutes les deux semaines le mardi a 9 h », « le 5 de chaque mois a 9 h ».
 *
 * Les dates se comptent a l'heure du fuseau du calendrier (`blog.ai.schedule_timezone`), changements d'heure
 * compris, et sortent en UTC. Calcul pur, sans requete : le calendrier (BlogGenerationCalendar) y ajoute le
 * dernier lot lance.
 */
final readonly class BlogGenerationSlots
{
    public function __construct(private string $timezone) {}

    /** Le premier creneau strictement apres `$moment`, au jour et a l'heure choisis, quelle que soit la periode. */
    public function firstAfter(BlogAiSettings $settings, CarbonImmutable $moment): CarbonImmutable
    {
        $local = $moment->setTimezone($this->timezone);

        $candidate = match ($settings->periodUnit) {
            BlogAiPeriodUnit::Day => $local->setTime($settings->hour, 0),
            BlogAiPeriodUnit::Week => $local->startOfDay()
                ->addDays(($settings->weekday - $local->dayOfWeekIso + 7) % 7)
                ->setTime($settings->hour, 0),
            BlogAiPeriodUnit::Month => $local->setDay($settings->monthDay)->setTime($settings->hour, 0),
        };

        if ($candidate->lessThanOrEqualTo($local)) {
            $candidate = $this->add($candidate, $settings->periodUnit, 1);
        }

        return $candidate->utc();
    }

    /** Le dernier creneau passe a `$now`, nul avant le premier creneau ou sans point de depart. */
    public function currentAt(BlogAiSettings $settings, CarbonImmutable $now): ?CarbonImmutable
    {
        if ($settings->startsAt === null) {
            return null;
        }

        $first = $this->firstAfter($settings, $settings->startsAt)->setTimezone($this->timezone);
        $local = $now->setTimezone($this->timezone);

        if ($local->lessThan($first)) {
            return null;
        }

        $periods = intdiv($this->unitsBetween($first, $local, $settings->periodUnit), $settings->periodCount);
        $slot = $this->add($first, $settings->periodUnit, $periods * $settings->periodCount);

        if ($slot->greaterThan($local)) {
            $slot = $this->add($first, $settings->periodUnit, ($periods - 1) * $settings->periodCount);
        }

        return $slot->utc();
    }

    /** Le creneau qui suit `$now` : le premier s'il n'est pas encore arrive, sinon une periode apres le dernier passe. */
    public function nextAfter(BlogAiSettings $settings, CarbonImmutable $now): CarbonImmutable
    {
        $current = $this->currentAt($settings, $now);

        if ($current === null) {
            return $this->firstAfter($settings, $settings->startsAt ?? $now);
        }

        return $this->add($current->setTimezone($this->timezone), $settings->periodUnit, $settings->periodCount)->utc();
    }

    /**
     * Le nombre d'unites entieres (jours, semaines ou mois du calendrier) entre deux dates du fuseau. Les jours se
     * comptent sur les dates, si bien qu'un jour de 23 ou 25 heures (changement d'heure) compte pour un.
     */
    private function unitsBetween(CarbonImmutable $from, CarbonImmutable $to, BlogAiPeriodUnit $unit): int
    {
        if ($unit === BlogAiPeriodUnit::Month) {
            return ($to->year * 12 + $to->month) - ($from->year * 12 + $from->month);
        }

        $days = (int) round(($to->startOfDay()->getTimestamp() - $from->startOfDay()->getTimestamp()) / 86_400);

        return $unit === BlogAiPeriodUnit::Week ? intdiv($days, 7) : $days;
    }

    /** Ajoute des unites a l'heure du fuseau : l'heure de l'horloge reste la meme, changement d'heure ou non. */
    private function add(CarbonImmutable $local, BlogAiPeriodUnit $unit, int $count): CarbonImmutable
    {
        return match ($unit) {
            BlogAiPeriodUnit::Day => $local->addDays($count),
            BlogAiPeriodUnit::Week => $local->addWeeks($count),
            BlogAiPeriodUnit::Month => $local->addMonthsNoOverflow($count),
        };
    }
}
