<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\AdminProfile;
use App\Livewire\Admin\LoginForm;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Admin\SubmissionList;
use App\Mail\AdminMessageToClient;
use App\Mail\StarterResponsiblePersonIssued;
use App\Mail\StarterResumeLink;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects guests from the back-office to the login', function () {
    get(route('admin.submissions.index'))->assertRedirect(route('admin.login'));
    get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('offers a link back to the public site on the login page', function () {
    get(route('admin.login'))
        ->assertOk()
        ->assertSee('Retour au site')
        ->assertSee(route('home'), false);
});

it('marks the back-office as noindex via HTTP header and meta tag', function () {
    get(route('admin.login'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('name="robots"', false)
        ->assertSee('noindex, nofollow', false);
});

it('sets the noindex header on authenticated back-office pages', function () {
    actingAs(User::factory()->create());

    get(route('admin.submissions.index'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('does not mark public pages as noindex', function () {
    get(route('home'))->assertHeaderMissing('X-Robots-Tag');
});

it('rejects invalid admin credentials', function () {
    User::factory()->create(['email' => 'admin@festilaw.com', 'password' => 'good-password']);

    Livewire::test(LoginForm::class)
        ->set('email', 'admin@festilaw.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('logs an admin in with valid credentials', function () {
    User::factory()->create(['email' => 'admin@festilaw.com', 'password' => 'good-password']);

    Livewire::test(LoginForm::class)
        ->set('email', 'admin@festilaw.com')
        ->set('password', 'good-password')
        ->call('login')
        ->assertRedirect(route('admin.submissions.index'));

    expect(auth()->check())->toBeTrue();
});

it('lists and filters dossiers for an authenticated admin', function () {
    $starter = Submission::factory()->starter()->create(['email' => 'buyer@example.com', 'company_name' => 'Buyer Co']);
    $other = Submission::factory()->starter()->create(['email' => 'other@example.com', 'company_name' => 'Other Co']);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionList::class)
        ->assertSee($starter->reference)
        ->assertSee($other->reference)
        ->set('search', 'buyer@example.com')
        ->assertSee($starter->reference)
        ->assertDontSee($other->reference);
});

it('keeps contacts out of the dossiers list and shows them on their own page', function () {
    $starter = Submission::factory()->starter()->create();
    Submission::factory()->create([
        'type' => SubmissionType::Contact,
        'first_name' => 'Zoe Martin',
        'email' => 'zoe@example.com',
    ]);

    actingAs(User::factory()->create());

    get(route('admin.submissions.index'))
        ->assertOk()
        ->assertSee($starter->reference)
        ->assertDontSee('zoe@example.com');

    get(route('admin.contacts.index'))
        ->assertOk()
        ->assertSee('Zoe Martin')
        ->assertSee('zoe@example.com')
        ->assertDontSee($starter->reference);
});

it('opens the detail when a list row is clicked', function () {
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionList::class)
        ->call('show', $submission->id)
        ->assertRedirect(route('admin.submissions.show', ['submission' => $submission->id]));
});

it('changes a submission status from the detail screen', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('newStatus', SubmissionStatus::Completed->value)
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Completed);
});

it('re-queries a failed payment from the admin detail and corrects a false failure', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    Mail::fake();
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid'])]);

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription, 'amount_cents' => 33300, 'service_year' => 2026,
        'currency' => 'EUR', 'provider' => 'stripe', 'provider_reference' => 'cs_1', 'status' => PaymentStatus::Failed,
    ]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('recheckPayment', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('refuses to set the Paid status manually (it is derived from payments)', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('newStatus', SubmissionStatus::Paid->value)
        ->call('updateStatus')
        ->assertHasErrors('newStatus');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);
});

it('orders the dossier status menu by workflow step, keeping Paid in place (not first)', function () {
    $submission = Submission::factory()->starter()->paid()->create(); // dossier déjà « Payé »

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->assertViewHas('statuses', fn (array $statuses): bool => array_map(fn (SubmissionStatus $s): string => $s->value, $statuses)
            === ['new', 'in_progress', 'awaiting_documents', 'awaiting_payment', 'paid', 'completed', 'cancelled']);
});

it('adds an internal note attributed to the current admin', function () {
    $submission = Submission::factory()->starter()->create();
    $admin = User::factory()->create();

    actingAs($admin);

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('noteBody', 'Rappeler le client demain matin.')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('noteBody', '');

    expect($submission->notes()->count())->toBe(1);
    expect($submission->notes()->first())
        ->body->toBe('Rappeler le client demain matin.')
        ->author_id->toBe($admin->id);
});

it('labels the resend action as a dossier link once the dossier is paid', function () {
    $paid = Submission::factory()->starter()->paid()->create();
    $inProgress = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $paid])
        ->assertSee('Renvoyer le lien du dossier')
        ->assertDontSee('Renvoyer le lien de reprise');

    Livewire::test(SubmissionDetail::class, ['submission' => $inProgress])
        ->assertSee('Renvoyer le lien de reprise');
});

it('resends the resume link to a starter client', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('resendLink');

    Mail::assertSent(StarterResumeLink::class, fn ($mail) => $mail->hasTo($submission->email));
});

it('resends the resume link in the language the client used', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create(['locale' => 'fr']);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('resendLink');

    Mail::assertSent(StarterResumeLink::class, fn ($mail) => $mail->hasTo($submission->email) && $mail->locale === 'fr');
});

it('sends a free-form email to the client from the detail screen', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('emailSubject', 'Un point sur votre dossier')
        ->set('emailBody', 'Bonjour, votre dossier avance bien.')
        ->call('sendEmail')
        ->assertHasNoErrors()
        ->assertSet('emailSubject', '')
        ->assertSet('emailBody', '')
        ->assertDispatched('email-sent')
        ->assertDispatched('admin-toast');

    Mail::assertSent(AdminMessageToClient::class, fn ($mail) => $mail->hasTo($submission->email)
        && $mail->hasSubject('Un point sur votre dossier'));
});

it('requires a subject and a message before sending an email', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('emailSubject', '')
        ->set('emailBody', '')
        ->call('sendEmail')
        ->assertHasErrors(['emailSubject' => 'required', 'emailBody' => 'required']);

    Mail::assertNothingSent();
});

it('shows admin validation errors in French', function () {
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('noteBody', '')
        ->call('addNote')
        ->assertHasErrors('noteBody')
        ->assertSee('Le contenu de la note est obligatoire.');
});

/** A complete, active STARTER dossier ready for RP issuance (succeeded payment + signed mandate + docs). */
function completeActiveDossier(): Submission
{
    return Submission::factory()->starter()->paid()->create();
}

/** An active STARTER dossier (succeeded subscription payment) but no signed mandate / documents yet. */
function activeStarterDossierWithoutMandate(): Submission
{
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);
    $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'service_year' => (int) now()->year,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_'.$submission->id,
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    return $submission->fresh();
}

it('issues the EU responsible person address, completes the dossier and emails the client', function () {
    Mail::fake();
    $submission = completeActiveDossier();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('rpAddress', "Festilaw SAS\n1 rue de l'Europe, 75001 Paris")
        ->call('issueResponsiblePerson')
        ->assertHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::Completed);
    expect($submission->eu_rp_address)->toContain('rue de l');

    Mail::assertSent(StarterResponsiblePersonIssued::class, fn ($mail) => $mail->hasTo($submission->email));
});

it('refuses to issue the RP when the dossier has no active payment', function () {
    Mail::fake();
    // Statut « Payé » en cache mais aucun paiement réussi : le dossier n'est pas actif.
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('rpAddress', 'Festilaw SAS, Paris')
        ->call('issueResponsiblePerson')
        ->assertDispatched('admin-toast', type: 'error');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
    Mail::assertNotSent(StarterResponsiblePersonIssued::class);
});

it('refuses to issue the RP when the mandate is not signed', function () {
    Mail::fake();
    $submission = activeStarterDossierWithoutMandate();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('rpAddress', 'Festilaw SAS, Paris')
        ->call('issueResponsiblePerson')
        ->assertDispatched('admin-toast', type: 'error');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
    Mail::assertNotSent(StarterResponsiblePersonIssued::class);
});

it('refuses to issue the RP when required documents are missing', function () {
    Mail::fake();
    $submission = activeStarterDossierWithoutMandate();
    // Mandat signé mais aucune pièce déposée.
    $submission->contract()->create([
        'filled_fields' => [],
        'signature_status' => SignatureStatus::Signed,
        'signature_provider' => 'stripe',
        'signature_provider_reference' => 'doc_'.$submission->id,
        'signed_file_path' => 'contracts/'.$submission->id.'.pdf',
        'signed_at' => now(),
    ]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission->fresh()])
        ->set('rpAddress', 'Festilaw SAS, Paris')
        ->call('issueResponsiblePerson')
        ->assertDispatched('admin-toast', type: 'error');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
    Mail::assertNotSent(StarterResponsiblePersonIssued::class);
});

it('issues the RP for a Pro dossier too (self-service, like Creator)', function () {
    Mail::fake();
    $submission = Submission::factory()->pro()->paid()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('rpAddress', 'Festilaw SAS, 1 rue de l\'Europe, Paris')
        ->call('issueResponsiblePerson')
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Completed);
    Mail::assertSent(StarterResponsiblePersonIssued::class, fn ($mail) => $mail->hasTo($submission->email));
});

it('resends the resume link to a Pro client too', function () {
    Mail::fake();
    $submission = Submission::factory()->pro()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('resendLink');

    Mail::assertSent(StarterResumeLink::class, fn ($mail) => $mail->hasTo($submission->email));
});

it('shows the Scale audit deduction badge once the 75 EUR audit is paid', function () {
    $submission = Submission::factory()->scale()->create();
    $submission->payments()->create([
        'type' => PaymentType::ScaleAudit, 'amount_cents' => 7500, 'currency' => 'EUR',
        'provider' => 'stripe', 'provider_reference' => 'cs_scale', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(),
    ]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->assertSee('à déduire du devis');
});

it('lets an admin record the confirmed Scale consultation slot and advance its status', function () {
    $submission = Submission::factory()->scale()->create();
    $submission->payments()->create([
        'type' => PaymentType::ScaleAudit, 'amount_cents' => 7500, 'currency' => 'EUR',
        'provider' => 'stripe', 'provider_reference' => 'cs_scale', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(),
    ]);
    $submission->appointment()->create(['status' => AppointmentStatus::Requested]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('apptScheduledAt', '2026-09-01T14:30')
        ->set('apptStatus', AppointmentStatus::Scheduled->value)
        ->call('updateAppointment')
        ->assertHasNoErrors();

    $appointment = $submission->appointment->fresh();
    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->scheduled_at->format('Y-m-d H:i'))->toBe('2026-09-01 14:30');
});

it('refuses to update an appointment on a dossier that has none', function () {
    $submission = Submission::factory()->scale()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->set('apptStatus', AppointmentStatus::Scheduled->value)
        ->call('updateAppointment')
        ->assertHasErrors('apptStatus');
});

it('presents a contact as an inquiry, not a dossier', function () {
    $contact = Submission::factory()->create([
        'type' => SubmissionType::Contact,
        'first_name' => 'Marie Dupont',
        'message' => 'Bonjour, une question sur vos services.',
    ]);

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $contact])
        ->assertSee('Prise de contact')
        ->assertSee('Marie Dupont')
        ->assertSee('Bonjour, une question sur vos services.')
        ->assertSee('Supprimer la prise de contact')
        ->assertDontSee('Statut du dossier')
        ->assertDontSee('Pièces')
        ->assertDontSee('Supprimer le dossier');
});

it('lets an admin update their email address', function () {
    $admin = User::factory()->create(['email' => 'old@festilaw.com']);
    actingAs($admin);

    Livewire::test(AdminProfile::class)
        ->set('email', 'new@festilaw.com')
        ->call('updateEmail')
        ->assertHasNoErrors();

    expect($admin->fresh()->email)->toBe('new@festilaw.com');
});

it('lets an admin change their password using the current one', function () {
    $admin = User::factory()->create(['password' => 'old-password-123']);
    actingAs($admin);

    Livewire::test(AdminProfile::class)
        ->set('current_password', 'old-password-123')
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('password', '');

    expect(Hash::check('new-password-456', $admin->fresh()->password))->toBeTrue();
});

it('rejects a password change when the current password is wrong', function () {
    $admin = User::factory()->create(['password' => 'old-password-123']);
    actingAs($admin);

    Livewire::test(AdminProfile::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-456')
        ->set('password_confirmation', 'new-password-456')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('old-password-123', $admin->fresh()->password))->toBeTrue();
});

it('redirects to the contacts list after deleting a contact', function () {
    $contact = Submission::factory()->create(['type' => SubmissionType::Contact]);
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $contact])
        ->call('deleteDossier')
        ->assertRedirect(route('admin.contacts.index'));

    expect(Submission::find($contact->id))->toBeNull();
});

it('deletes a dossier and redirects to the list', function () {
    $submission = Submission::factory()->starter()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('deleteDossier')
        ->assertRedirect(route('admin.submissions.index'));

    expect(Submission::find($submission->id))->toBeNull();
});

/** A paid dossier whose subscription covered $serviceYear (so a renewal may be due). */
function adminPaidDossier(int $serviceYear): Submission
{
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid,
        'company_name' => 'Renewco',
    ]);
    Payment::factory()->succeeded()->for($submission)->create([
        'type' => PaymentType::StarterSubscription,
        'service_year' => $serviceYear,
    ]);

    return $submission->fresh();
}

it('shows the derived dossier state (renewal-aware) in the dossiers list', function () {
    actingAs(User::factory()->create());
    adminPaidDossier((int) now()->year - 1); // paye l'an dernier -> a renouveler

    Livewire::test(SubmissionList::class)
        ->assertSee('État')
        ->assertSee('En retard'); // 21 juillet : grace de janvier depassee -> etat "En retard"
});

it('filters the list down to dossiers needing renewal', function () {
    actingAs(User::factory()->create());
    adminPaidDossier((int) now()->year - 1); // Renewco : a renouveler
    $upToDate = adminPaidDossier((int) now()->year); // paye cette annee : a jour
    $upToDate->update(['company_name' => 'Freshpaid']);

    Livewire::test(SubmissionList::class)
        ->set('state', 'renewal')
        ->assertSee('Renewco')
        ->assertDontSee('Freshpaid');
});

it('filters the list to up-to-date active dossiers only', function () {
    actingAs(User::factory()->create());
    adminPaidDossier((int) now()->year - 1); // Renewco : a renouveler (exclu)
    $upToDate = adminPaidDossier((int) now()->year); // a jour (inclus)
    $upToDate->update(['company_name' => 'Freshpaid']);

    Livewire::test(SubmissionList::class)
        ->set('state', 'active')
        ->assertSee('Freshpaid')
        ->assertDontSee('Renewco');
});

it('files a completed dossier by its renewal status (a served client due for renewal stays visible)', function () {
    actingAs(User::factory()->create());

    // "Termine" (RP delivree) et "a renouveler" sont orthogonaux. Un dossier termine A JOUR s'affiche
    // "Termine" ; un dossier termine DU doit rester visible sous "A renouveler" (sinon un client servi
    // relance par ProcessRenewals disparaitrait du back-office). Jamais sous "Actif" (badge = Termine).
    $completedCurrent = adminPaidDossier((int) now()->year); // couvre l'annee -> "Termine"
    $completedCurrent->update(['status' => SubmissionStatus::Completed, 'company_name' => 'DoneThisYear']);
    $completedOld = adminPaidDossier((int) now()->year - 1); // ne couvre pas -> "a renouveler"
    $completedOld->update(['status' => SubmissionStatus::Completed, 'company_name' => 'DoneLastYear']);

    Livewire::test(SubmissionList::class)
        ->set('state', 'active')
        ->assertDontSee('DoneThisYear')
        ->assertDontSee('DoneLastYear')
        ->set('state', 'renewal')
        ->assertSee('DoneLastYear')       // termine mais du -> reste visible en renouvellement
        ->assertDontSee('DoneThisYear')
        ->set('state', 'completed')
        ->assertSee('DoneThisYear')       // termine et a jour
        ->assertDontSee('DoneLastYear');
});

it('shows the renewal section on the dossier detail', function () {
    actingAs(User::factory()->create());
    $dossier = adminPaidDossier((int) now()->year - 1);

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier])
        ->assertSee('Renouvellement')
        ->assertSee('Payé jusqu\'à l\'année')
        ->assertSee((string) (now()->year - 1)); // annee payee
});

/** A signed contract whose signed PDF was never downloaded (transient provider failure at signing time). */
function dossierWithSignedMandateMissingPdf(): Submission
{
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingDocuments]);
    Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Signed,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'doc_'.fake()->uuid(),
        'signed_file_path' => null,
        'signed_at' => now(),
    ]);

    return $submission->fresh();
}

it('offers a recovery button when the mandate is signed but its PDF is missing', function () {
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => dossierWithSignedMandateMissingPdf()])
        ->assertSee('Mandat signé, PDF manquant')
        ->assertSee('Récupérer le PDF signé');
});

it('does not offer the recovery button once the signed PDF is present', function () {
    actingAs(User::factory()->create());
    $submission = Submission::factory()->starter()->create();
    Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Signed,
        'signed_file_path' => 'contracts/existing.pdf',
        'signed_at' => now(),
    ]);

    Livewire::test(SubmissionDetail::class, ['submission' => $submission->fresh()])
        ->assertDontSee('Mandat signé, PDF manquant')
        ->assertSee('Mandat signé'); // le document, pas l'alerte
});

it('re-downloads the signed PDF from the recovery button', function () {
    $gateway = Mockery::mock(SignatureGatewayInterface::class);
    $gateway->shouldReceive('key')->andReturn('signwell');
    $gateway->shouldReceive('downloadSignedDocument')->once()->andReturn('contracts/recovered.pdf');
    app()->instance(SignatureGatewayInterface::class, $gateway);

    actingAs(User::factory()->create());
    $submission = dossierWithSignedMandateMissingPdf();

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('recoverSignedMandate')
        ->assertHasNoErrors()
        ->assertDispatched('admin-toast');

    expect($submission->contract->fresh()->signed_file_path)->toBe('contracts/recovered.pdf');
});

it('reports an error and keeps the file empty when the provider still has no PDF', function () {
    $gateway = Mockery::mock(SignatureGatewayInterface::class);
    $gateway->shouldReceive('key')->andReturn('signwell');
    $gateway->shouldReceive('downloadSignedDocument')->once()->andReturnNull();
    app()->instance(SignatureGatewayInterface::class, $gateway);

    actingAs(User::factory()->create());
    $submission = dossierWithSignedMandateMissingPdf();

    Livewire::test(SubmissionDetail::class, ['submission' => $submission])
        ->call('recoverSignedMandate')
        ->assertDispatched('admin-toast', type: 'error');

    expect($submission->contract->fresh()->signed_file_path)->toBeNull();
});

it('does not show a payments section on a contact detail', function () {
    actingAs(User::factory()->create());
    $contact = Submission::factory()->create([
        'type' => SubmissionType::Contact, 'first_name' => 'Zoe', 'email' => 'zoe@example.com', 'message' => 'Bonjour',
    ]);

    Livewire::test(SubmissionDetail::class, ['submission' => $contact])
        ->assertSee(__('Coordonnées'))     // c'est bien une prise de contact
        ->assertDontSee(__('Paiements'));  // pas de section paiement
});

it('shows the payments section on a real dossier detail', function () {
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => Submission::factory()->starter()->create()])
        ->assertSee(__('Paiements'));
});
