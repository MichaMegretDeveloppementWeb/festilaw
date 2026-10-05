<?php

declare(strict_types=1);

use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Web\Funnel\PackChangePanel;
use App\Mail\FunnelNotification;
use App\Mail\PackDowngradeApproved;
use App\Mail\PackDowngradeRejected;
use App\Mail\RenewalReminder;
use App\Models\PackChange;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 | Retour du Pro au Creator apres paiement (SC12) : le client le demande (justificatif de CA recent +
 | attestation d'eligibilite), Festilaw valide ou refuse (le dernier mot), et le changement s'applique au
 | renouvellement : dossier en Creator, tarif Creator, nouveau mandat Creator a signer avant de renouveler.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    Storage::fake('local');
    Mail::fake();
});

/** Un client Pro paye pour l'annee en cours, mandat Pro signe. */
function activePro(int $paidYear = 2026): Submission
{
    $dossier = Submission::factory()->pro()->paid($paidYear)->create([
        'resume_token' => 'protok',
        'locale' => 'en',
        'email' => 'pro@example.com',
    ]);
    $dossier->contract->update(['pack' => SubmissionType::Pro, 'filled_fields' => ['activity' => 'furniture']]);

    return $dossier->fresh();
}

function requestDowngrade(Submission $dossier): void
{
    Livewire::test(PackChangePanel::class, ['submission' => $dossier])
        ->set('requestingDowngrade', true)
        ->set('turnoverProof', UploadedFile::fake()->create('turnover-2026.pdf', 120, 'application/pdf'))
        ->set('eligibilityConfirmed', true)
        ->call('requestDowngrade')
        ->assertHasNoErrors();
}

it('offers an active Pro client to request the Creator pack from the next renewal', function () {
    activePro();

    get(route('my-project', ['dossier' => 'protok']))
        ->assertOk()
        ->assertSee('9 products or fewer?')
        ->assertSee('Request the Creator Pack from your next renewal');
});

it('requires a proof of turnover and the eligibility statement', function () {
    Livewire::test(PackChangePanel::class, ['submission' => activePro()])
        ->set('requestingDowngrade', true)
        ->call('requestDowngrade')
        ->assertHasErrors(['turnoverProof', 'eligibilityConfirmed']);

    expect(PackChange::count())->toBe(0);
});

it('records the request with the new proof of turnover and tells Festilaw, without changing anything yet', function () {
    $dossier = activePro();

    requestDowngrade($dossier);

    $request = PackChange::sole();
    expect($request->status)->toBe(PackChangeStatus::Requested)
        ->and($request->to_pack)->toBe(SubmissionType::Starter)
        ->and($request->effective_year)->toBe(2027)
        ->and($request->eligibility_confirmed_at)->not->toBeNull()
        ->and($dossier->fresh()->type)->toBe(SubmissionType::Pro)
        ->and($dossier->uploadedDocuments()->where('type', DocumentType::TurnoverProof)->sole()->original_filename)->toBe('turnover-2026.pdf');
    Mail::assertSent(FunnelNotification::class, fn (FunnelNotification $mail) => $mail->reason === FunnelNotificationReason::PackDowngradeRequested);

    Livewire::test(PackChangePanel::class, ['submission' => $dossier->fresh()])
        ->assertSee('Your request to switch to the Creator Pack is with Festilaw')
        ->call('withdrawDowngrade')
        ->assertSee('Your request is withdrawn: you keep the Pro Pack.');
    expect($request->fresh()->status)->toBe(PackChangeStatus::Withdrawn);
});

it('adds the proof of turnover when the Pro file had none', function () {
    $dossier = activePro();
    $dossier->uploadedDocuments()->where('type', DocumentType::TurnoverProof)->delete();

    requestDowngrade($dossier);

    expect($dossier->uploadedDocuments()->where('type', DocumentType::TurnoverProof)->count())->toBe(1);
});

it('lets Festilaw approve the request: Pro until 31 December, client told by email', function () {
    $dossier = activePro();
    requestDowngrade($dossier);
    actingAs($admin = User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->assertSee('Demande de passage au Creator à valider')
        ->call('approvePackDowngrade', PackChange::sole()->id)
        ->assertDispatched('admin-toast', type: 'success');

    $request = PackChange::sole();
    expect($request->status)->toBe(PackChangeStatus::Approved)
        ->and($request->decided_by)->toBe($admin->id)
        ->and($request->effective_year)->toBe(2027)
        ->and($dossier->fresh()->type)->toBe(SubmissionType::Pro);
    Mail::assertSent(PackDowngradeApproved::class, fn (PackDowngradeApproved $mail) => $mail->hasTo('pro@example.com')
        && str_contains($mail->render(), '1 January 2027')
        && str_contains($mail->render(), '€333'));

    Livewire::test(PackChangePanel::class, ['submission' => $dossier->fresh()])
        ->assertSee('Confirmed: your file switches to the Creator Pack from 1 January 2027');
});

it('lets Festilaw reject the request with a message to the client', function () {
    $dossier = activePro();
    requestDowngrade($dossier);
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->set('packDowngradeNote', 'Your turnover proof shows more than 35,000 EUR.')
        ->call('rejectPackDowngrade', PackChange::sole()->id)
        ->assertDispatched('admin-toast', type: 'success');

    expect(PackChange::sole()->status)->toBe(PackChangeStatus::Rejected)
        ->and(PackChange::sole()->note)->toBe('Your turnover proof shows more than 35,000 EUR.');
    Mail::assertSent(PackDowngradeRejected::class, fn (PackDowngradeRejected $mail) => str_contains($mail->render(), 'more than 35,000 EUR'));

    Livewire::test(PackChangePanel::class, ['submission' => $dossier->fresh()])
        ->assertSee('Your last request to switch to the Creator Pack was not accepted');
});

it('switches the file to Creator at the renewal, with a new Creator mandate to sign before renewing', function () {
    $dossier = activePro();
    $proMandate = $dossier->contract;
    requestDowngrade($dossier);
    actingAs(User::factory()->create());
    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])->call('approvePackDowngrade', PackChange::sole()->id);
    auth()->logout();

    $this->travelTo(now()->setDate(2027, 1, 2)->setTime(7, 0));
    $this->artisan('festilaw:process-renewals')->assertOk();

    $dossier->refresh();
    expect($dossier->type)->toBe(SubmissionType::Starter)
        ->and($dossier->contract->pack)->toBe(SubmissionType::Starter)
        ->and($dossier->contract->signature_status)->toBe(SignatureStatus::Pending)
        ->and($dossier->contract->filled_fields['activity'])->toBe('furniture')
        ->and($proMandate->fresh()->role)->toBe(ContractRole::Superseded)
        ->and(PackChange::sole()->status)->toBe(PackChangeStatus::Applied);
    Mail::assertSent(RenewalReminder::class, fn (RenewalReminder $mail) => str_contains($mail->render(), '€333'));

    // L'espace demande la signature du mandat Creator ; pas de renouvellement avant.
    get(route('my-project', ['dossier' => 'protok']))
        ->assertOk()
        ->assertSee('Sign your Creator Pack mandate')
        ->assertDontSee('to renew');
    post(route('get-started.starter.renew', ['dossier' => 'protok']))->assertRedirect();
    expect(Payment::where('type', PaymentType::AnnualRenewal)->count())->toBe(0);

    // Mandat Creator signe : le renouvellement au tarif Creator est propose.
    $dossier->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => now()]);
    get(route('my-project', ['dossier' => 'protok']))->assertSee('Pay €333 to renew');
});

it('applies the switch at once when the renewal is already due at approval', function () {
    $this->travelTo(now()->setDate(2027, 1, 5));
    $dossier = activePro(2026); // paye 2026, 2027 du
    requestDowngrade($dossier);
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])->call('approvePackDowngrade', PackChange::sole()->id);

    expect(PackChange::sole()->status)->toBe(PackChangeStatus::Applied)
        ->and(PackChange::sole()->effective_year)->toBe(2027)
        ->and($dossier->fresh()->type)->toBe(SubmissionType::Starter);
});

it('does not apply a request withdrawn by the client after approval', function () {
    $dossier = activePro();
    requestDowngrade($dossier);
    actingAs(User::factory()->create());
    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])->call('approvePackDowngrade', PackChange::sole()->id);
    Livewire::test(PackChangePanel::class, ['submission' => $dossier->fresh()])->call('withdrawDowngrade');

    $this->travelTo(now()->setDate(2027, 1, 2));
    $this->artisan('festilaw:process-renewals')->assertOk();

    expect($dossier->fresh()->type)->toBe(SubmissionType::Pro)
        ->and(PackChange::sole()->status)->toBe(PackChangeStatus::Withdrawn);
});
