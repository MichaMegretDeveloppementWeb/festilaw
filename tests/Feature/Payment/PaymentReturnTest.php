<?php

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

/*
 | Retour du prestataire de paiement : URL signee cle sur le paiement (jamais le token du dossier, qui change
 | a chaque renvoi du lien), qui redirige vers la page du dossier avec son token ACTUEL.
 */

uses(RefreshDatabase::class);

function pendingPaymentFor(Submission $submission, PaymentType $type): Payment
{
    return $submission->payments()->create([
        'type' => $type,
        'amount_cents' => 1000,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
    ]);
}

it('brings the buyer back to the right dossier page with the current token, even after the link was resent', function (string $pack, PaymentType $type, string $route, string $successFlag, string $cancelFlag) {
    $submission = Submission::factory()->{$pack}()->create(['resume_token' => 'oldtoken', 'resume_expires_at' => now()->addDays(30)]);
    $payment = pendingPaymentFor($submission, $type);
    [$successUrl, $cancelUrl] = app(PaymentReturnService::class)->returnUrls($payment);

    // Le lien du dossier est renvoye pendant le paiement : le token change.
    $submission->regenerateResumeToken();
    $currentToken = $submission->fresh()->resume_token;
    expect($currentToken)->not->toBe('oldtoken');

    get($successUrl)->assertRedirect(route($route, ['dossier' => $currentToken, $successFlag => 1]));
    get($cancelUrl)->assertRedirect(route($route, ['dossier' => $currentToken, $cancelFlag => 1]));
})->with([
    'year 1' => ['starter', PaymentType::StarterSubscription, 'get-started.starter.journey', 'payment_return', 'payment_cancelled'],
    'renewal' => ['starter', PaymentType::AnnualRenewal, 'my-project', 'renewal_return', 'renewal_cancelled'],
    'scale audit' => ['scale', PaymentType::ScaleAudit, 'get-started.scale.space', 'audit_return', 'audit_cancelled'],
]);

it('refuses a return URL without a signature', function () {
    $payment = pendingPaymentFor(Submission::factory()->starter()->create(), PaymentType::StarterSubscription);

    get(route('get-started.payment.return', ['payment' => $payment->id, 'status' => 'success']))->assertForbidden();
});

it('refuses a tampered return URL', function () {
    $payment = pendingPaymentFor(Submission::factory()->starter()->create(), PaymentType::StarterSubscription);
    [$successUrl] = app(PaymentReturnService::class)->returnUrls($payment);

    get(str_replace('status=success', 'status=cancelled', $successUrl))->assertForbidden();
});

it('refuses an expired return URL', function () {
    $payment = pendingPaymentFor(Submission::factory()->starter()->create(), PaymentType::StarterSubscription);
    [$successUrl] = app(PaymentReturnService::class)->returnUrls($payment);

    $this->travel(4)->days();

    get($successUrl)->assertForbidden();
});
