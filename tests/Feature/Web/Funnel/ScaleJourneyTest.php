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
 | Parcours SCALE : l'audit (75 EUR) se paie d'abord, puis la consultation se reserve dans la page de
 | reservation Google integree a l'espace Scale. L'agenda n'est jamais propose avant le paiement.
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

/** A SCALE dossier whose audit is paid (the step before booking the consultation). */
function paidScaleDossier(string $token = 'scaletok'): Submission
{
    $dossier = scaleDossier($token);
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);
    $dossier->update(['status' => SubmissionStatus::InProgress]);

    return $dossier->fresh();
}

/** A SCALE dossier booked BEFORE paying, as the order in force until October 2026 allowed. */
function bookedUnpaidScaleDossier(string $token = 'scaletok'): Submission
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

    Mail::assertSent(ScaleSpaceLink::class, fn (ScaleSpaceLink $mail) => $mail->hasTo('bigco@example.com')
        && str_contains($mail->render(), 'pay the €75 audit fee and book your consultation'));
});

it('opens the Scale space on the payment step, saying the consultation is booked right after paying', function () {
    scaleDossier();

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Audit to pay')
        ->assertSee('Pay your expert audit to unlock your consultation booking.')
        ->assertSee(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertSee('After payment, you\'ll come straight back here to pick your consultation slot in our calendar.')
        ->assertDontSee('scale-calendar__frame', false)
        ->assertDontSee(SCALE_TEST_CALENDAR)                                                  // aucune URL d'agenda avant paiement
        ->assertDontSee(route('get-started.scale.book', ['dossier' => 'scaletok']));
});

it('refuses to book the consultation before the audit is paid', function () {
    $dossier = scaleDossier();

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error', 'Please pay the €75 audit fee before booking your consultation.');

    expect($dossier->appointment()->count())->toBe(0)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::New);

    Mail::assertNotSent(ScaleConsultationBooked::class);
    Mail::assertNotSent(FunnelNotification::class);
});

it('starts the audit checkout with an idempotency key, Scale return URLs and the booking note', function () {
    fakeStripeCreate();
    scaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect('https://checkout.stripe.test/cs_scale');

    $audit = Payment::where('type', PaymentType::ScaleAudit)->sole();
    expect($audit->amount_cents)->toBe(7500)
        ->and($audit->status)->toBe(PaymentStatus::Pending);

    // Retour signe (cle : le paiement), qui redirige ensuite vers l'espace Scale avec le token courant. Le mot
    // sous le bouton Stripe rappelle que la consultation se reserve juste apres.
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/checkout/sessions')
        && $req->hasHeader('Idempotency-Key')
        && str_contains(urldecode($req->body()), 'get-started/payment/'.$audit->id.'/return')
        && str_contains(urldecode($req->body()), 'signature=')
        && str_contains(urldecode($req->body()), 'custom_text[submit][message]=After payment, you\'ll return to your Scale space to book your consultation.'));

    get(app(PaymentReturnService::class)->returnUrls($audit)[0])
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok', 'audit_return' => 1]));
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

it('confirms the audit on return and opens the embedded booking calendar (not the subscription "paid")', function () {
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

    get(route('get-started.scale.space', ['dossier' => 'scaletok', 'audit_return' => 1]))
        ->assertOk()
        ->assertSee('Payment received · now pick your consultation slot below.')
        ->assertSee('Consultation to book')
        ->assertSee('<iframe class="scale-calendar__frame" src="'.SCALE_TEST_CALENDAR.'?gv=true"', false)
        ->assertSee('referrerpolicy="no-referrer"', false)
        ->assertSee('href="'.SCALE_TEST_CALENDAR.'" target="_blank"', false)                // repli : nouvel onglet
        ->assertSee(route('get-started.scale.book', ['dossier' => 'scaletok']))              // "J'ai reserve"
        ->assertDontSee(route('get-started.scale.pay', ['dossier' => 'scaletok']));

    $dossier->refresh();
    expect($dossier->payments()->where('type', PaymentType::ScaleAudit)->sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and($dossier->status)->toBe(SubmissionStatus::InProgress) // l'audit n'est pas un abonnement
        ->and($dossier->isActive())->toBeFalse();                   // pas de couverture RP ouverte

    Mail::assertSent(ScaleAuditConfirmed::class, fn (ScaleAuditConfirmed $mail) => $mail->hasTo('bigco@example.com')
        && ! $mail->booked
        && str_contains($mail->render(), 'The next step is to book your video consultation'));
});

it('only shows the "payment received" banner on the return from the checkout', function () {
    paidScaleDossier();

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Your audit is paid. Two quick steps to lock in your video consultation:')
        ->assertDontSee('Payment received');
});

it('falls back to opening the calendar in a new tab when the configured URL cannot be embedded', function (string $calendarUrl) {
    config()->set('festilaw.scale.calendar_url', $calendarUrl);
    paidScaleDossier();

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

it('records the consultation booking once the audit is paid, idempotently, and confirms both parties', function () {
    $dossier = paidScaleDossier();

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_booked');
    // Second clic : pas de doublon (unique par dossier), et donc pas de second e-mail.
    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertRedirect();

    expect($dossier->appointment()->count())->toBe(1)
        ->and($dossier->appointment->status)->toBe(AppointmentStatus::Requested)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::InProgress);

    Mail::assertSent(ScaleConsultationBooked::class, 1);
    Mail::assertSent(ScaleConsultationBooked::class, fn (ScaleConsultationBooked $mail) => $mail->hasTo('bigco@example.com')
        && str_contains($mail->render(), 'Our team will confirm the exact slot by email'));
    Mail::assertSent(FunnelNotification::class, fn ($mail) => $mail->reason === FunnelNotificationReason::ConsultationBooked);

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Consultation requested')   // statut client traduit, pas le libelle du back-office
        ->assertDontSee('Demandé')
        ->assertSee('Our team will confirm the exact slot by email')
        ->assertDontSee('scale-calendar__frame', false)
        ->assertDontSee(route('get-started.scale.pay', ['dossier' => 'scaletok']));
});

it('shows the booking banner right after booking', function () {
    $dossier = paidScaleDossier();
    Appointment::factory()->for($dossier)->create();

    $this->withSession(['scale_booked' => true])
        ->get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSee('Thanks · your booking request is recorded.');
});

it('lets a dossier booked before paying (former order) pay, without offering the calendar again', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_scale', 'status' => 'complete', 'payment_status' => 'paid']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_scale', 'url' => 'https://checkout.stripe.test/cs_scale']),
    ]);
    $dossier = bookedUnpaidScaleDossier();

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertOk()
        ->assertSee('Audit to pay')
        ->assertSee('Consultation requested')
        ->assertSee('Your consultation request is recorded. Pay your expert audit to confirm it.')
        ->assertSee(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertDontSee('After payment, you\'ll come straight back here')
        ->assertDontSee(SCALE_TEST_CALENDAR);

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect('https://checkout.stripe.test/cs_scale');

    // Retour paye : la consultation deja reservee est confirmee, sans repasser par l'agenda.
    get(route('get-started.scale.space', ['dossier' => 'scaletok', 'audit_return' => 1]))
        ->assertOk()
        ->assertSee('Our team will confirm the exact slot by email')
        ->assertDontSee('Payment received')
        ->assertDontSee('scale-calendar__frame', false);

    expect($dossier->payments()->where('type', PaymentType::ScaleAudit)->sole()->status)->toBe(PaymentStatus::Succeeded);
    Mail::assertSent(ScaleAuditConfirmed::class, fn (ScaleAuditConfirmed $mail) => $mail->booked
        && str_contains($mail->render(), 'and so is your consultation'));
});

it('refuses to start a second audit payment once the audit is paid', function () {
    paidScaleDossier();

    post(route('get-started.scale.pay', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error', 'Your audit is already paid · you can book your consultation.');

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

it('refuses to book on a cancelled Scale dossier', function () {
    $dossier = paidScaleDossier();
    $dossier->update(['status' => SubmissionStatus::Cancelled]);

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))
        ->assertRedirect(route('get-started.scale.space', ['dossier' => 'scaletok']))
        ->assertSessionHas('scale_error');

    expect($dossier->appointment()->count())->toBe(0)
        ->and($dossier->fresh()->status)->toBe(SubmissionStatus::Cancelled);
});

it('does not downgrade a completed Scale dossier when a booking is recorded', function () {
    $dossier = paidScaleDossier();
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
