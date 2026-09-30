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
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentReturnService;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 | Parcours SCALE : la consultation se reserve d'abord (page de reservation Google integree a l'espace
 | Scale), puis le paiement de l'audit (75 EUR) vient la confirmer.
 */

uses(RefreshDatabase::class);

const SCALE_TEST_CALENDAR = 'https://calendar.google.com/calendar/appointments/schedules/TestSchedule123';

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    config()->set('festilaw.scale.calendar_url', SCALE_TEST_CALENDAR);
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

/** A SCALE dossier whose consultation is already booked (the step before paying the audit). */
function bookedScaleDossier(string $token = 'scaletok'): Submission
{
    $dossier = scaleDossier($token);
    Appointment::factory()->for($dossier)->create();
    $dossier->update(['status' => SubmissionStatus::InProgress]);

    return $dossier->fresh();
}

it('opens a SCALE dossier with a magic link, emails it and lands the visitor in the space', function () {
    Livewire::test(ScaleForm::class)
        ->set('company_name', 'Bigco')
        ->set('first_name', 'Dana')
        ->set('last_name', 'Rivera')
        ->set('email', 'bigco@example.com')
        ->call('submit')
        ->assertRedirect();

    $submission = Submission::where('email', 'bigco@example.com')->sole();

    expect($submission->type)->toBe(SubmissionType::Scale)
        ->and($submission->resume_token)->not->toBeNull()
        ->and($submission->resume_expires_at)->not->toBeNull();

    Mail::assertSent(ScaleSpaceLink::class, fn ($mail) => $mail->hasTo('bigco@example.com'));
});

it('opens the Scale space on the booking step, with the Google booking page embedded', function () {
    scaleDossier();

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Consultation to book')
        ->assertSee('<iframe class="scale-calendar__frame" src="'.SCALE_TEST_CALENDAR.'?gv=true"', false)
        ->assertSee('referrerpolicy="no-referrer"', false)
        ->assertSee('href="'.SCALE_TEST_CALENDAR.'" target="_blank"', false)                // repli : nouvel onglet
        ->assertSee(route('get-started.scale.book', ['dossier' => 'scaletok']))              // "J'ai reserve"
        ->assertDontSee(route('get-started.scale.pay', ['dossier' => 'scaletok']));          // pas de paiement avant
});

it('falls back to opening the calendar in a new tab when the configured URL cannot be embedded', function (string $calendarUrl) {
    config()->set('festilaw.scale.calendar_url', $calendarUrl);
    scaleDossier();

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertDontSee('scale-calendar__frame', false)
        ->assertSee('Open the booking calendar')
        ->assertSee('href="'.$calendarUrl.'" target="_blank"', false);
})->with([
    'short link' => 'https://calendar.app.google/w8ZejYQLkZfgAo3F7',
    'placeholder' => 'https://calendar.google.com/',
    'other site' => 'https://example.com/calendar/appointments/schedules/abc',
]);

it('404s the Scale space for a non-Scale dossier', function () {
    Submission::factory()->starter()->create(['resume_token' => 'startertok', 'resume_expires_at' => now()->addDays(30)]);

    get(route('get-started.scale.space', ['dossier' => 'startertok']))->assertNotFound();
});

it('records a consultation booking before the audit is paid, idempotently, and points to the payment', function () {
    $dossier = scaleDossier();

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_booked');
    // Second clic : pas de doublon (unique par dossier), et donc pas de second e-mail.
    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertRedirect();

    expect($dossier->appointment()->count())->toBe(1)
        ->and($dossier->appointment->status)->toBe(AppointmentStatus::Requested)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::InProgress);

    // Confirmation au client (une seule fois malgre le double clic), qui l'envoie payer + notification equipe.
    Mail::assertSent(ScaleConsultationBooked::class, 1);
    Mail::assertSent(ScaleConsultationBooked::class, fn (ScaleConsultationBooked $mail) => $mail->hasTo('bigco@example.com')
        && ! $mail->auditPaid
        && str_contains($mail->render(), 'One last step to confirm it'));
    Mail::assertSent(FunnelNotification::class, fn ($mail) => $mail->reason === FunnelNotificationReason::ConsultationBooked);

    // L'espace passe a l'etape paiement.
    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Audit to pay')
        ->assertSee('Consultation requested')   // statut client traduit, pas le libelle du back-office
        ->assertDontSee('Demandé')
        ->assertSee('Pay your expert audit to confirm it')
        ->assertSee(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertDontSee('scale-calendar__frame', false);
});

it('shows the "last step: pay" banner right after booking', function () {
    bookedScaleDossier();

    $this->withSession(['scale_booked' => true])
        ->get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSee('Last step: pay your audit to confirm it.');
});

it('refuses to pay the audit before the consultation is booked', function () {
    fakeStripeCreate();
    scaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error', 'Please book your consultation before paying the audit fee.');

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(0);
    Http::assertNothingSent();
});

it('starts the audit checkout with an idempotency key and Scale return URLs', function () {
    fakeStripeCreate();
    bookedScaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect('https://checkout.stripe.test/cs_scale');

    $audit = Payment::where('type', PaymentType::ScaleAudit)->sole();
    expect($audit->amount_cents)->toBe(7500)
        ->and($audit->status)->toBe(PaymentStatus::Pending);

    // Retour signe (cle : le paiement), qui redirige ensuite vers l'espace Scale avec le token courant.
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/checkout/sessions')
        && $req->hasHeader('Idempotency-Key')
        && str_contains(urldecode($req->body()), 'get-started/payment/'.$audit->id.'/return')
        && str_contains(urldecode($req->body()), 'signature='));

    get(app(PaymentReturnService::class)->returnUrls($audit)[0])
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok', 'audit_return' => 1]));
});

it('reuses the pending audit checkout instead of creating a second one (anti double-debit)', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_scale', 'status' => 'open', 'url' => 'https://checkout.stripe.test/cs_scale']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_scale', 'url' => 'https://checkout.stripe.test/cs_scale']),
    ]);
    bookedScaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))->assertRedirect();
    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))->assertRedirect();

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(1);
});

it('confirms the audit on return, confirming the booked consultation (not the subscription "paid")', function () {
    $dossier = bookedScaleDossier();
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

    get(route('get-started.scale.space', ['dossier' => 'scaletok', 'audit_return' => 1]))
        ->assertOk()
        ->assertSee('Consultation requested')
        ->assertSee('Our team will confirm the exact slot by email');

    $dossier->refresh();
    expect($dossier->payments()->where('type', PaymentType::ScaleAudit)->sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and($dossier->status)->toBe(SubmissionStatus::InProgress) // l'audit n'est pas un abonnement
        ->and($dossier->isActive())->toBeFalse();                   // pas de couverture RP ouverte

    Mail::assertSent(ScaleAuditConfirmed::class, fn (ScaleAuditConfirmed $mail) => $mail->hasTo('bigco@example.com')
        && $mail->booked
        && str_contains($mail->render(), 'and so is your consultation'));
});

it('invites a dossier paid before booking (former order) to book, without asking to pay again', function () {
    $dossier = scaleDossier();
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Your audit is paid. Two quick steps to lock in your video consultation:')
        ->assertSee('scale-calendar__frame', false)
        ->assertDontSee(route('get-started.scale.pay', ['dossier' => 'scaletok']));

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertSessionHas('scale_booked');

    // Audit deja paye : l'e-mail de reservation ne demande pas de payer.
    Mail::assertSent(ScaleConsultationBooked::class, fn (ScaleConsultationBooked $mail) => $mail->auditPaid
        && ! str_contains($mail->render(), 'One last step to confirm it'));
});

it('refuses to start a second audit payment once the audit is paid', function () {
    $dossier = bookedScaleDossier();
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error', 'Your audit is already paid.');

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(1);
});

it('refuses to pay the audit on a cancelled Scale dossier', function () {
    fakeStripeCreate();
    $dossier = bookedScaleDossier();
    $dossier->update(['status' => SubmissionStatus::Cancelled]);

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error');

    expect(Payment::where('type', PaymentType::ScaleAudit)->count())->toBe(0);
});

it('refuses to book on a cancelled Scale dossier', function () {
    $dossier = scaleDossier();
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
        ->assertDontSee(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertDontSee(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertDontSee('scale-calendar__frame', false);
});

it('404s an expired Scale space link (capability binding)', function () {
    Submission::factory()->scale()->create(['resume_token' => 'expiredtok', 'resume_expires_at' => now()->subDay()]);

    get(route('get-started.scale.space', ['dossier' => 'expiredtok']))->assertNotFound();
});
