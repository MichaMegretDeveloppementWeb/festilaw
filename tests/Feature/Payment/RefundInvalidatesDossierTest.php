<?php

use App\Actions\Web\Payment\MarkPaymentRefundedAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use App\Services\Starter\StarterDossierFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** A paid dossier reachable at the token 'refundme', with one succeeded subscription payment. */
function refundableDossier(): Submission
{
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid,
        'resume_token' => 'refundme',
        'resume_expires_at' => null,
        'email' => 'refunded@example.com',
    ]);
    $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'service_year' => (int) now()->year,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_refund',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    return $submission->fresh();
}

it('expires the magic link of a dossier that a refund leaves inactive', function () {
    $dossier = refundableDossier();

    app(MarkPaymentRefundedAction::class)->execute($dossier->payments()->first());

    $dossier->refresh();
    expect($dossier->isActive())->toBeFalse()
        ->and($dossier->resume_expires_at)->not->toBeNull()
        ->and($dossier->resume_expires_at->isFuture())->toBeFalse();

    // Le lien magique ne resout plus rien (binding {dossier} = resumable -> 404).
    get(route('my-project', ['dossier' => 'refundme']))->assertNotFound();
});

it('keeps the magic link when another payment still keeps the dossier active', function () {
    $dossier = refundableDossier();
    // Un renouvellement paye couvre encore le dossier : le remboursement de l'annee 1 ne le tue pas.
    $dossier->payments()->create([
        'type' => PaymentType::AnnualRenewal,
        'amount_cents' => 33300,
        'service_year' => (int) now()->year + 1,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_renew',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);
    $yearOne = $dossier->payments()->where('type', PaymentType::StarterSubscription)->first();

    app(MarkPaymentRefundedAction::class)->execute($yearOne);

    $dossier->refresh();
    expect($dossier->isActive())->toBeTrue()          // le renouvellement le maintient actif
        ->and($dossier->resume_expires_at)->toBeNull(); // lien intact
});

it('lets a refunded client start a fresh dossier (dedup no longer surfaces the dead one)', function () {
    $dossier = refundableDossier();

    app(MarkPaymentRefundedAction::class)->execute($dossier->payments()->first());

    // Plus aucun dossier resumable/actif pour cet email -> une nouvelle demande repartira a neuf.
    expect(app(StarterDossierFinder::class)->mostRelevantResumableForEmail('refunded@example.com'))->toBeNull();
});
