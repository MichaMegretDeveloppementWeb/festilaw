<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

use Carbon\CarbonImmutable;

/**
 * Unite de la periode de la generation automatique (reglage du back-office, pas une colonne) :
 * « un lot tous les N jours, semaines ou mois ».
 */
enum BlogAiPeriodUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Day => __('jour(s)'),
            self::Week => __('semaine(s)'),
            self::Month => __('mois'),
        };
    }

    /**
     * La date a partir de laquelle un nouveau lot est du, `$count` periodes apres `$from`. Au mois, un lot ramene
     * au dernier jour d'un mois court (31 janvier, puis 28 fevrier) retrouve son jour d'origine ensuite (31 mars),
     * au lieu de glisser au 28 de chaque mois (controle du 20/11).
     *
     * @param  int|null  $anchorDay  le jour du mois du premier lot, pour un rythme au mois
     */
    public function addTo(CarbonImmutable $from, int $count, ?int $anchorDay = null): CarbonImmutable
    {
        if ($this !== self::Month) {
            return $this === self::Day ? $from->addDays($count) : $from->addWeeks($count);
        }

        $due = $from->addMonthsNoOverflow($count);

        if ($anchorDay === null || $anchorDay <= $from->day || $from->day !== $from->daysInMonth) {
            return $due;
        }

        return $due->setDay(min($anchorDay, $due->daysInMonth));
    }
}
