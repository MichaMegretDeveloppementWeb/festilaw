<?php

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Services\Blog\Generation\BlogGenerationSlots;
use Carbon\CarbonImmutable;

/*
 | Creneaux de la generation automatique, sans Laravel (decision de Micha du 26/11) : le premier creneau suit
 | strictement le point de depart, au jour et a l'heure choisis, puis un creneau toutes les N periodes. Les heures
 | sont celles de Paris, et les dates sortent en UTC.
 */

function slotSettings(BlogAiPeriodUnit $unit, int $count, string $startsAt, int $hour = 9, int $weekday = 2, int $monthDay = 5): BlogAiSettings
{
    return new BlogAiSettings(
        enabled: true,
        periodCount: $count,
        periodUnit: $unit,
        articlesPerRun: 1,
        pendingDraftsCap: 3,
        monthlySpendCapCents: 1_000,
        models: [],
        imageMode: BlogAiImageMode::Stock,
        maxImages: 1,
        notificationEmail: 'team@example.com',
        articleWords: 1_000,
        effort: BlogEffortLevel::Medium,
        weekday: $weekday,
        monthDay: $monthDay,
        hour: $hour,
        startsAt: CarbonImmutable::parse($startsAt, 'UTC'),
    );
}

function slots(): BlogGenerationSlots
{
    return new BlogGenerationSlots('Europe/Paris');
}

function utc(?CarbonImmutable $moment): ?string
{
    return $moment?->utc()->toDateTimeString();
}

it('puts the first slot strictly after the starting point, on the chosen day and hour', function (BlogAiPeriodUnit $unit, string $startsAt, string $first) {
    $settings = slotSettings($unit, 1, $startsAt);

    expect(utc(slots()->firstAfter($settings, $settings->startsAt)))->toBe($first);
})->with([
    // Jeudi 26/11/2026 a 17 h a Paris : le prochain mardi 9 h, le prochain 5 du mois, le lendemain 9 h.
    'a week, from a Thursday' => [BlogAiPeriodUnit::Week, '2026-11-26 16:00:00', '2026-12-01 08:00:00'],
    'a week, on the Tuesday at 9:00 itself' => [BlogAiPeriodUnit::Week, '2026-12-01 08:00:00', '2026-12-08 08:00:00'],
    'a week, earlier on the Tuesday' => [BlogAiPeriodUnit::Week, '2026-12-01 07:00:00', '2026-12-01 08:00:00'],
    'a month, after the 5th' => [BlogAiPeriodUnit::Month, '2026-11-26 16:00:00', '2026-12-05 08:00:00'],
    'a month, before the 5th' => [BlogAiPeriodUnit::Month, '2026-12-02 10:00:00', '2026-12-05 08:00:00'],
    'a day, after the hour' => [BlogAiPeriodUnit::Day, '2026-11-26 16:00:00', '2026-11-27 08:00:00'],
    'a day, before the hour' => [BlogAiPeriodUnit::Day, '2026-11-26 07:00:00', '2026-11-26 08:00:00'],
]);

it('finds the last slot passed and the next one, every N periods', function (BlogAiPeriodUnit $unit, int $count, string $now, ?string $current, string $next) {
    // Point de depart le jeudi 26/11 : premiers creneaux le mardi 1/12, le 5/12 ou le vendredi 27/11.
    $settings = slotSettings($unit, $count, '2026-11-26 16:00:00');
    $at = CarbonImmutable::parse($now, 'UTC');

    expect(utc(slots()->currentAt($settings, $at)))->toBe($current)
        ->and(utc(slots()->nextAfter($settings, $at)))->toBe($next);
})->with([
    'before the first slot' => [BlogAiPeriodUnit::Week, 1, '2026-11-30 12:00:00', null, '2026-12-01 08:00:00'],
    'on the first slot' => [BlogAiPeriodUnit::Week, 1, '2026-12-01 08:00:00', '2026-12-01 08:00:00', '2026-12-08 08:00:00'],
    'every week, a few days later' => [BlogAiPeriodUnit::Week, 1, '2026-12-10 12:00:00', '2026-12-08 08:00:00', '2026-12-15 08:00:00'],
    'every 2 weeks, in the week between' => [BlogAiPeriodUnit::Week, 2, '2026-12-10 12:00:00', '2026-12-01 08:00:00', '2026-12-15 08:00:00'],
    'every 3 days' => [BlogAiPeriodUnit::Day, 3, '2026-12-01 09:00:00', '2026-11-30 08:00:00', '2026-12-03 08:00:00'],
    'every month, just before the 5th' => [BlogAiPeriodUnit::Month, 1, '2027-01-05 07:59:59', '2026-12-05 08:00:00', '2027-01-05 08:00:00'],
    'every 3 months' => [BlogAiPeriodUnit::Month, 3, '2027-02-10 12:00:00', '2026-12-05 08:00:00', '2027-03-05 08:00:00'],
]);

it('keeps the hour of Paris across the change to summer time', function () {
    // 9 h a Paris : 8 h UTC en hiver, 7 h UTC apres le 28 mars 2027.
    $settings = slotSettings(BlogAiPeriodUnit::Week, 1, '2027-03-20 12:00:00', weekday: 1);

    expect(utc(slots()->currentAt($settings, CarbonImmutable::parse('2027-03-22 08:00:00', 'UTC'))))->toBe('2027-03-22 08:00:00')
        ->and(utc(slots()->nextAfter($settings, CarbonImmutable::parse('2027-03-22 08:00:00', 'UTC'))))->toBe('2027-03-29 07:00:00')
        ->and(utc(slots()->currentAt($settings, CarbonImmutable::parse('2027-03-29 07:00:00', 'UTC'))))->toBe('2027-03-29 07:00:00');
});

it('has no slot passed without a starting point', function () {
    $settings = slotSettings(BlogAiPeriodUnit::Week, 1, '2026-11-26 16:00:00');
    $withoutStart = new BlogAiSettings(...[...get_object_vars($settings), 'startsAt' => null]);

    expect(slots()->currentAt($withoutStart, CarbonImmutable::parse('2027-01-01 00:00:00', 'UTC')))->toBeNull();
});
