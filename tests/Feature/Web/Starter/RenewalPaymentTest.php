<?php

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    // Checkout Stripe bouchonne : creation (POST) et reprise de session ouverte (GET par id).
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_renew', 'status' => 'open', 'url' => 'https://checkout.stripe.test/cs_renew']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_renew', 'url' => 'https://checkout.stripe.test/cs_renew']),
    ]);
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
    // Un client qui renouvelle a forcement signe son mandat en annee 1 (prerequis du renouvellement).
    Contract::factory()->for($submission)->signed()->create();

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

it('reuses the pending renewal checkout instead of creating a duplicate (anti double-debit)', function () {
    renewableDossier(now()->year - 1);

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))->assertRedirect();
    post(route('get-started.starter.renew', ['dossier' => 'renewme']))->assertRedirect();

    expect(Payment::where('type', PaymentType::AnnualRenewal)->count())->toBe(1);
});

it('refuses a second renewal while one is awaiting async confirmation (anti double-debit)', function () {
    $dossier = renewableDossier(now()->year - 1);
    // Un renouvellement asynchrone (Klarna/Bancontact) engage cote prestataire, en attente de reglement.
    Payment::factory()->for($dossier)->create([
        'type' => PaymentType::AnnualRenewal,
        'service_year' => now()->year,
        'status' => PaymentStatus::Processing,
    ]);

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))
        ->assertRedirect(route('my-project', ['dossier' => 'renewme']))
        ->assertSessionHas('renewal_error');

    // Aucun 2e renouvellement cree : toujours le seul paiement Processing.
    expect(Payment::where('type', PaymentType::AnnualRenewal)->count())->toBe(1);
});

it('charges the full Pro fee on a Pro renewal', function () {
    renewableDossier(now()->year - 1, 'pro');

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))->assertRedirect();

    $renewal = Payment::where('type', PaymentType::AnnualRenewal)->sole();
    expect($renewal->amount_cents)->toBe(120000);
});

it('refuses to start a renewal for a dossier that never signed its mandate (no payment without prerequisites)', function () {
    // Dossier "paye" en base mais SANS mandat signe (donnee incoherente / bypass) : le renouvellement
    // doit etre refuse, aucun paiement ne doit demarrer.
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid, 'resume_token' => 'renewme', 'resume_expires_at' => null,
    ]);
    Payment::factory()->succeeded()->for($submission)->create(['type' => PaymentType::StarterSubscription, 'service_year' => now()->year - 1]);
    // Pas de contrat signe.

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))
        ->assertRedirect(route('my-project', ['dossier' => 'renewme']))
        ->assertSessionHas('renewal_error');

    expect(Payment::where('type', PaymentType::AnnualRenewal)->count())->toBe(0);
});

it('refuses to start a renewal when the subscription is up to date', function () {
    renewableDossier(now()->year); // deja paye pour l'annee en cours

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))
        ->assertRedirect(route('my-project', ['dossier' => 'renewme']))
        ->assertSessionHas('renewal_error');

    expect(Payment::where('type', PaymentType::AnnualRenewal)->count())->toBe(0);
});

it('never leaks an unexpected error: friendly message, no 500', function () {
    $dossier = renewableDossier(now()->year - 1);

    // Panne technique inattendue (comme la QueryException de contrainte vue en prod) : on casse la
    // table pour provoquer une vraie erreur BDD. Le filet catch(Throwable) doit l'attraper.
    Schema::drop('payments');

    post(route('get-started.starter.renew', ['dossier' => 'renewme']))
        ->assertRedirect(route('my-project', ['dossier' => 'renewme']))
        ->assertSessionHas('renewal_error');

    expect($dossier->fresh()->status)->toBe(SubmissionStatus::Paid); // dossier intact, pas de 500
});
