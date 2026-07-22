<?php

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\StarterPaymentConfirmed;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('is idempotent on redelivery: a second confirmation neither re-emails nor moves paid_at', function () {
    Mail::fake();

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'service_year' => 2026,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Pending,
    ]);

    $action = app(MarkPaymentSucceededAction::class);

    $action->execute($payment);
    $paidAt = $payment->fresh()->paid_at;

    // Seconde livraison du meme webhook : doit etre un no-op (l'update conditionnel n'affecte plus de ligne).
    $action->execute($payment->fresh());

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Paid);

    // L'email de confirmation ne part qu'une seule fois, malgre les deux livraisons.
    Mail::assertSent(StarterPaymentConfirmed::class, 1);
});
