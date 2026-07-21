<?php

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['fake']);
    Mail::fake();
});

/** A paid dossier whose last subscription payment covered $serviceYear (so a renewal may be due). */
function renewableDossier(int $serviceYear, string $state = 'starter'): Submission
{
    $submission = Submission::factory()->{$state}()->create([
        'status' => SubmissionStatus::Paid,
        'resume_token' => 'renewme',
        'resume_expires_at' => null,
        'locale' => 'en',
    ]);
    Payment::factory()->succeeded()->for($submission)->create([
        'type' => PaymentType::StarterSubscription,
        'service_year' => $serviceYear,
    ]);

    return $submission->fresh();
}

it('starts a full-fee renewal payment from the dossier when a year is due', function () {
    $dossier = renewableDossier(now()->year - 1); // paye l'an dernier -> du cette annee

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))
        ->assertRedirect(); // vers le checkout du provider

    $renewal = $dossier->payments()->where('type', PaymentType::AnnualRenewal)->sole();

    expect($renewal->amount_cents)->toBe(33300) // plein tarif Creator, pas de prorata
        ->and($renewal->service_year)->toBe(now()->year)
        ->and($renewal->status)->toBe(PaymentStatus::Pending);
});

it('charges the full Pro fee on a Pro renewal', function () {
    renewableDossier(now()->year - 1, 'pro');

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))->assertRedirect();

    $renewal = Payment::where('type', PaymentType::AnnualRenewal)->sole();
    expect($renewal->amount_cents)->toBe(120000);
});

it('refuses to start a renewal when the subscription is up to date', function () {
    renewableDossier(now()->year); // deja paye pour l'annee en cours

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))
        ->assertRedirect(route('my-project', ['dossier' => 'renewme']))
        ->assertSessionHas('renewal_error');

    expect(Payment::where('type', PaymentType::AnnualRenewal)->count())->toBe(0);
});
