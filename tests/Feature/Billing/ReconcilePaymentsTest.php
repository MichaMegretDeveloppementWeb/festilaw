<?php

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Le registre est un singleton lu a la construction. Comme .env de test peut porter
    // PAYMENT_PROVIDERS=stripe, on force le rebuild sur 'fake' apres avoir change la config.
    config()->set('payment.enabled', ['fake']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
});

/** A pending payment with the given overrides, attached to a fresh dossier. */
function pendingPayment(array $attrs = []): Payment
{
    $createdAt = $attrs['created_at'] ?? now()->subHour();
    unset($attrs['created_at']);

    $payment = Payment::factory()->for(Submission::factory()->starter())->create(array_merge([
        'status' => PaymentStatus::Pending,
        'provider' => 'fake',
        'provider_reference' => 'cs_test_'.fake()->uuid(),
    ], $attrs));

    // created_at passe a create() n'est pas fiable ici : on le fige explicitement (age = eligibilite).
    $payment->created_at = $createdAt;
    $payment->save();

    return $payment;
}

it('checks only stale pending payments with a provider reference', function () {
    pendingPayment(); // éligible : en attente, ancien, avec référence
    pendingPayment(['created_at' => now()]); // trop récent → ignoré
    pendingPayment(['provider_reference' => null]); // sans référence → ignoré
    pendingPayment(['status' => PaymentStatus::Succeeded]); // déjà réussi → ignoré

    $this->artisan('festilaw:reconcile-payments')
        ->expectsOutputToContain('verifies : 1')
        ->assertOk();
});

it('dry run writes nothing and confirms nothing', function () {
    pendingPayment();

    $this->artisan('festilaw:reconcile-payments', ['--dry' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertOk();

    expect(Payment::where('status', PaymentStatus::Succeeded)->count())->toBe(0);
});
