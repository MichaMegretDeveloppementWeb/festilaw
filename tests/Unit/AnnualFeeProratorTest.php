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

it('never bills below the provider minimum for a tiny test tariff', function () {
    $prorator = new AnnualFeeProrator;

    // Tarif de test a 1 € : le prorata de decembre (1/12 ≈ 8 c) tomberait sous le minimum Stripe (0,50 €)
    // et ferait echouer le checkout -> plancher a 50 c (config festilaw.payment.min_charge_cents).
    expect($prorator->firstYearCents(100, Carbon::create(2026, 12, 15)))->toBe(50)
        ->and($prorator->firstYearCents(100, Carbon::create(2026, 1, 10)))->toBe(100);
});
