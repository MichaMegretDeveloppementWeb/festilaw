<?php

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Actions\Web\Scale\CreateScaleSubmissionAction;
use App\Actions\Web\Scale\RecordAppointmentAction;
use App\Actions\Web\Scale\StartScaleAuditPaymentAction;
use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Data\Payment\CheckoutSessionData;
use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['fake']);
});

it('opens a PRO file into the same self-service journey as Creator', function () {
    Mail::fake();

    $outcome = app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Acme Goods',
        'first_name' => 'Dana',
        'email' => 'acme@example.com',
    ], SubmissionType::Pro);

    expect($outcome->isNew)->toBeTrue()
        ->and($outcome->submission->type)->toBe(SubmissionType::Pro)
        ->and($outcome->submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($outcome->submission->resume_token)->not->toBeNull()
        ->and($outcome->submission->contract)->not->toBeNull();
});

it('runs the SCALE audit-then-appointment flow with the fake provider', function () {
    Mail::fake();

    $submission = app(CreateScaleSubmissionAction::class)->execute([
        'company_name' => 'Bigco',
        'email' => 'bigco@example.com',
    ]);
    expect($submission->type)->toBe(SubmissionType::Scale)
        ->and($submission->status)->toBe(SubmissionStatus::New);

    // Pay the audit (fake) -> pending payment.
    $checkout = app(StartScaleAuditPaymentAction::class)->execute($submission, 'fake');
    expect($checkout)->toBeInstanceOf(CheckoutSessionData::class);

    $payment = $submission->fresh()->payments->first();
    expect($payment->type)->toBe(PaymentType::ScaleAudit)
        ->and($payment->amount_cents)->toBe(7500);

    // Payment webhook confirms -> Paid.
    app(MarkPaymentSucceededAction::class)->execute($payment->fresh());
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);

    // Book the consultation -> InProgress + appointment.
    $appointment = app(RecordAppointmentAction::class)->execute($submission->fresh());
    expect($appointment->status)->toBe(AppointmentStatus::Requested)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});
