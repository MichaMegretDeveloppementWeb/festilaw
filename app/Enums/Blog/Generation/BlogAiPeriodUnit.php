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

    /** La date a partir de laquelle un nouveau lot est du, `$count` periodes apres `$from`. */
    public function addTo(CarbonImmutable $from, int $count): CarbonImmutable
    {
        return match ($this) {
            self::Day => $from->addDays($count),
            self::Week => $from->addWeeks($count),
            self::Month => $from->addMonthsNoOverflow($count),
        };
    }
}
