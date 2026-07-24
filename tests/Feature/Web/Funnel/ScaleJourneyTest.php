<?php

use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Web\Funnel\ScaleForm;
use App\Mail\FunnelNotification;
use App\Mail\ScaleAuditConfirmed;
use App\Mail\ScaleConsultationBooked;
use App\Mail\ScaleSpaceLink;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    Mail::fake();
});

/** Stubs the Stripe checkout-session creation (POST). */
function fakeStripeCreate(): void
{
    Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_scale', 'url' => 'https://checkout.stripe.test/cs_scale'])]);
}

/** A SCALE dossier reachable at its token. */
function scaleDossier(string $token = 'scaletok'): Submission
{
    return Submission::factory()->scale()->create([
        'status' => SubmissionStatus::New,
        'resume_token' => $token,
        'resume_expires_at' => now()->addDays(30),
        'email' => 'bigco@example.com',
        'locale' => 'en',
    ]);
}

it('opens a SCALE dossier with a magic link, emails it and lands the visitor in the space', function () {
    Livewire::test(ScaleForm::class)
        ->set('company_name', 'Bigco')
        ->set('first_name', 'Dana')
        ->set('email', 'bigco@example.com')
        ->call('submit')
        ->assertRedirect();

    $submission = Submission::where('email', 'bigco@example.com')->sole();

    expect($submission->type)->toBe(SubmissionType::Scale)
        ->and($submission->resume_token)->not->toBeNull()
        ->and($submission->resume_expires_at)->not->toBeNull();

    Mail::assertSent(ScaleSpaceLink::class, fn ($mail) => $mail->hasTo('bigco@example.com'));
});

it('shows the pay step (pay form) on the Scale space when the audit is unpaid', function () {
    scaleDossier();

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee(route('get-started.scale.pay', ['dossier' => 'scaletok'])); // l'action du formulaire de paiement
});

it('404s the Scale space for a non-Scale dossier', function () {
    $starter = Submission::factory()->starter()->create(['resume_token' => 'startertok', 'resume_expires_at' => now()->addDays(30)]);

    get(route('get-started.scale.space', ['dossier' => 'startertok']))->assertNotFound();
});

it('starts the audit checkout with an idempotency key and Scale return URLs', function () {
    fakeStripeCreate();
    scaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect('https://checkout.stripe.test/cs_scale');

    $audit = Payment::where('type', PaymentType::ScaleAudit)->sole();
    expect($audit->amount_cents)->toBe(7500)
        ->and($audit->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/checkout/sessions')
        && $req->hasHeader('Idempotency-Key')
        && str_contains(urldecode($req->body()), 'get-started/scale/scaletok')
        && str_contains(urldecode($req->body()), 'audit_return'));
});

it('reuses the pending audit checkout instead of creating a second one (anti double-debit)', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_scale', 'status' => 'open', 'url' => 'https://checkout.stripe.test/cs_scale']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_scale', 'url' => 'https://checkout.stripe.test/cs_scale']),
    ]);
    scaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))->assertRedirect();
    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))->assertRedirect();

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(1);
});

it('confirms the audit on return, advancing the dossier to in-progress (not the subscription "paid")', function () {
    $dossier = scaleDossier();
    $dossier->payments()->create([
        'type' => PaymentType::ScaleAudit,
        'amount_cents' => 7500,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_scale',
        'status' => PaymentStatus::Pending,
    ]);
    // Le provider dit "paye" au retour.
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_scale', 'status' => 'complete', 'payment_status' => 'paid'])]);

    get(route('get-started.scale.space', ['dossier' => 'scaletok', 'audit_return' => 1]))->assertOk();

    $dossier->refresh();
    expect($dossier->payments()->where('type', PaymentType::ScaleAudit)->sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and($dossier->status)->toBe(SubmissionStatus::InProgress) // l'audit n'est pas un abonnement
        ->and($dossier->isActive())->toBeFalse();                   // pas de couverture RP ouverte

    Mail::assertSent(ScaleAuditConfirmed::class, fn ($mail) => $mail->hasTo('bigco@example.com'));
});

it('records a consultation booking once the audit is paid, idempotently, and confirms both parties', function () {
    $dossier = scaleDossier();
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_booked');
    // Second clic : pas de doublon (unique par dossier), et donc pas de second e-mail.
    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertRedirect();

    expect($dossier->appointment()->count())->toBe(1)
        ->and($dossier->appointment->status)->toBe(AppointmentStatus::Requested);

    // Confirmation au client (une seule fois malgre le double clic) + notification a l'equipe.
    Mail::assertSent(ScaleConsultationBooked::class, 1);
    Mail::assertSent(ScaleConsultationBooked::class, fn ($mail) => $mail->hasTo('bigco@example.com'));
    Mail::assertSent(FunnelNotification::class, fn ($mail) => $mail->reason === FunnelNotificationReason::ConsultationBooked);
});

it('refuses to book a consultation before the audit is paid', function () {
    scaleDossier();

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error');

    expect(Submission::where('resume_token', 'scaletok')->sole()->appointment()->count())->toBe(0);
});

it('refuses to start a second audit payment once the audit is paid', function () {
    $dossier = scaleDossier();
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error');

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(1);
});

it('refuses to pay the audit on a cancelled Scale dossier', function () {
    fakeStripeCreate();
    $dossier = scaleDossier();
    $dossier->update(['status' => SubmissionStatus::Cancelled]);

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error');

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(0);
});

it('refuses to book on a cancelled Scale dossier even when the audit was paid', function () {
    $dossier = scaleDossier();
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);
    $dossier->update(['status' => SubmissionStatus::Cancelled]);

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error');

    expect($dossier->appointment()->count())->toBe(0)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::Cancelled);
});

it('does not downgrade a completed Scale dossier when a booking is recorded', function () {
    $dossier = scaleDossier();
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);
    $dossier->update(['status' => SubmissionStatus::Completed]);

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertRedirect();

    // Le rendez-vous est bien enregistre, mais "Termine" n'est jamais retrograde en "en cours".
    expect($dossier->appointment()->count())->toBe(1)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::Completed);
});

it('renders the cancelled state on the Scale space (no pay or book form)', function () {
    $dossier = scaleDossier();
    $dossier->update(['status' => SubmissionStatus::Cancelled]);

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('cancelled')
        ->assertDontSee(route('get-started.scale.pay', ['dossier' => 'scaletok']));
});

it('404s an expired Scale space link (capability binding)', function () {
    Submission::factory()->scale()->create(['resume_token' => 'expiredtok', 'resume_expires_at' => now()->subDay()]);

    get(route('get-started.scale.space', ['dossier' => 'expiredtok']))->assertNotFound();
});
