<?php

declare(strict_types=1);

use App\Services\Billing\AnnualFeeProrator;
use Carbon\Carbon;

it('bills the full year when signed in January', function () {
    $prorator = new AnnualFeeProrator;

    expect($prorator->remainingMonths(Carbon::create(2026, 1, 10)))->toBe(12)
        ->and($prorator->firstYearCents(33300, Carbon::create(2026, 1, 10)))->toBe(33300);
});

it('bills six months when signed in July (the contract example)', function () {
    $prorator = new AnnualFeeProrator;

    expect($prorator->remainingMonths(Carbon::create(2026, 7, 15)))->toBe(6)
        ->and($prorator->firstYearCents(33300, Carbon::create(2026, 7, 15)))->toBe(16650);
});

it('bills one month when signed in December', function () {
    $prorator = new AnnualFeeProrator;

    expect($prorator->remainingMonths(Carbon::create(2026, 12, 31)))->toBe(1)
        ->and($prorator->firstYearCents(33300, Carbon::create(2026, 12, 31)))->toBe(2775);
});

it('prorates the Pro annual fee too', function () {
    $prorator = new AnnualFeeProrator;

    // 120000 * 6/12 = 60000
    expect($prorator->firstYearCents(120000, Carbon::create(2026, 7, 1)))->toBe(60000);
});
