<?php

use App\Enums\Submission\SubmissionType;
use Tests\TestCase;

// annualCents() lit la config : on boote l'app Laravel pour ce fichier.
uses(TestCase::class);

it('exposes the annual fee for the two online packs', function () {
    config()->set('festilaw.starter.amount_cents', 33300);
    config()->set('festilaw.pro.amount_cents', 120000);

    expect(SubmissionType::Starter->annualCents())->toBe(33300)
        ->and(SubmissionType::Pro->annualCents())->toBe(120000);
});

it('throws when a non-pack type is asked for an annual fee', function () {
    SubmissionType::Scale->annualCents();
})->throws(LogicException::class);

it('marks Creator and Pro as the online self-service journeys', function () {
    expect(SubmissionType::Starter->hasOnlineJourney())->toBeTrue()
        ->and(SubmissionType::Pro->hasOnlineJourney())->toBeTrue()
        ->and(SubmissionType::Scale->hasOnlineJourney())->toBeFalse()
        ->and(SubmissionType::Contact->hasOnlineJourney())->toBeFalse();
});
