<?php

declare(strict_types=1);

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureEventOutcome;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Web\Funnel\PackChangePanel;
use App\Mail\FunnelNotification;
use App\Mail\PackUpgradeConfirmed;
use App\Models\Contract;
use App\Models\PackChange;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Passage du Creator au Pro apres paiement (SC12) : le client seul, depuis son espace. Il signe un mandat Pro
 | (signature integree), paie l'ecart de tarif au prorata des mois restants, et le dossier passe au Pro a la
 | confirmation du paiement. Festilaw est prevenue et garde le dernier mot (annulation + remboursement).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    Mail::fake();
});

/** Un client Creator paye pour l'annee en cours (a jour), mandat Creator signe. */
function activeCreator(string $token = 'creatortok'): Submission
{
    $dossier = Submission::factory()->starter()->paid(2026)->create([
        'resume_token' => $token,
        'locale' => 'en',
        'email' => 'creator@example.com',
    ]);
    $dossier->contract->update([
        'pack' => SubmissionType::Starter,
        'filled_fields' => ['incorporation_place' => 'Toronto, Canada', 'founding_year' => '2015', 'activity' => 'ceramics'],
    ]);

    return $dossier->fresh();
}

/** Prestataire de signature bouchonne : signe ou non. */
function bindUpgradeSignature(bool $signed): void
{
    app()->bind(SignatureGatewayInterface::class, fn () => new class($signed) implements SignatureGatewayInterface
    {
        public function __construct(private bool $signed) {}

        public function key(): string
        {
            return 'stub';
        }

        public function createSigningSession(Contract $contract): SigningSessionData
        {
            return new SigningSessionData('pro-ref', 'https://example.com/sign-pro');
        }

        public function currentSigningUrl(Contract $contract): ?string
        {
            return $this->signed ? null : 'https://example.com/sign-pro';
        }

        public function checkStatus(Contract $contract): SignatureWebhookData
        {
            return new SignatureWebhookData('pro-ref', $this->signed ? SignatureEventOutcome::Signed : SignatureEventOutcome::Unresolved);
        }

        public function parseWebhook(Request $request): SignatureWebhookData
        {
            return new SignatureWebhookData('pro-ref', SignatureEventOutcome::Unresolved);
        }

        public function downloadSignedDocument(Contract $contract): ?string
        {
            return null;
        }
    });
}

/** Une montee au mandat Pro deja signe et au paiement de la difference en attente. */
function upgradeAwaitingPayment(Submission $dossier): Payment
{
    $contract = $dossier->contracts()->create([
        'pack' => SubmissionType::Pro,
        'role' => ContractRole::Pending,
        'signature_status' => SignatureStatus::Signed,
        'signature_provider' => 'stub',
        'signature_provider_reference' => 'pro-ref',
        'signed_at' => now(),
        'signed_file_path' => 'contracts/pro-ref.pdf',
    ]);
    $payment = $dossier->payments()->create([
        'type' => PaymentType::PackUpgrade,
        'amount_cents' => 21675,
        'service_year' => 2026,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_up',
        'status' => PaymentStatus::Pending,
    ]);
    $dossier->packChanges()->create([
        'from_pack' => SubmissionType::Starter,
        'to_pack' => SubmissionType::Pro,
        'status' => PackChangeStatus::Open,
        'contract_id' => $contract->id,
        'payment_id' => $payment->id,
        'amount_cents' => 21675,
    ]);

    return $payment;
}

it('quotes the price gap over the remaining months of the year (October: 3/12)', function () {
    expect(app(PackChangeService::class)->upgradeQuoteCents())->toBe(21675); // (120000 - 33300) x 3/12
});

it('offers the switch to Pro to an up-to-date Creator client only', function () {
    activeCreator();
    get(route('my-project', ['dossier' => 'creatortok']))->assertOk()->assertSee('More than 9 products?');

    // Renouvellement du (paye pour l'an dernier seulement) : pas de montee avant d'avoir renouvele.
    Submission::factory()->starter()->paid(2025)->create(['resume_token' => 'duetok', 'locale' => 'en']);
    get(route('my-project', ['dossier' => 'duetok']))->assertOk()->assertDontSee('More than 9 products?');

    Submission::factory()->pro()->paid(2026)->create(['resume_token' => 'protok', 'locale' => 'en']);
    get(route('my-project', ['dossier' => 'protok']))->assertOk()->assertDontSee('More than 9 products?');
});

it('opens the switch with a pending Pro mandate, keeps the Creator pack until paid, and tells Festilaw', function () {
    $dossier = activeCreator();

    Livewire::test(PackChangePanel::class, ['submission' => $dossier])
        ->set('confirmingUpgrade', true)
        ->assertSee('€216.75')
        ->call('startUpgrade')
        ->call('startUpgrade') // double clic : une seule montee
        ->assertSee('Step 1 of 2');

    $dossier->refresh();
    $upgrade = PackChange::sole();
    expect($upgrade->status)->toBe(PackChangeStatus::Open)
        ->and($upgrade->contract->pack)->toBe(SubmissionType::Pro)
        ->and($upgrade->contract->role)->toBe(ContractRole::Pending)
        ->and($upgrade->contract->filled_fields['activity'])->toBe('ceramics')
        ->and($dossier->type)->toBe(SubmissionType::Starter)
        ->and($dossier->contract->pack)->toBe(SubmissionType::Starter);

    Mail::assertSent(FunnelNotification::class, 1);
    Mail::assertSent(FunnelNotification::class, fn (FunnelNotification $mail) => $mail->reason === FunnelNotificationReason::PackUpgradeStarted);
});

it('signs the Pro mandate on the page, then asks to pay the difference', function () {
    $dossier = activeCreator();
    bindUpgradeSignature(signed: false);
    $panel = Livewire::test(PackChangePanel::class, ['submission' => $dossier])->call('startUpgrade');

    $panel->call('sign')->assertDispatched('open-signing', url: 'https://example.com/sign-pro');
    expect(PackChange::sole()->contract->signature_provider_reference)->toBe('pro-ref');

    bindUpgradeSignature(signed: true);
    $panel->call('signingCompleted')
        ->assertSee('Step 2 of 2')
        ->assertSee('Pay €216.75');

    expect(PackChange::sole()->contract->signature_status)->toBe(SignatureStatus::Signed)
        ->and($dossier->fresh()->type)->toBe(SubmissionType::Starter);
});

it('refuses to take the payment before the Pro mandate is signed', function () {
    $dossier = activeCreator();

    Livewire::test(PackChangePanel::class, ['submission' => $dossier])
        ->call('startUpgrade')
        ->call('pay')
        ->assertHasErrors('pack');

    expect(Payment::where('type', PaymentType::PackUpgrade)->count())->toBe(0);
});

it('takes the payment of the difference through Stripe, reusing an open checkout', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_up', 'status' => 'open', 'url' => 'https://checkout.stripe.test/cs_up']),
        '*/v1/checkout/sessions' => Http::response(['id' => 'cs_up', 'url' => 'https://checkout.stripe.test/cs_up']),
    ]);
    $dossier = activeCreator();
    Livewire::test(PackChangePanel::class, ['submission' => $dossier])->call('startUpgrade');
    PackChange::sole()->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => now()]);

    Livewire::test(PackChangePanel::class, ['submission' => $dossier])->call('pay')->assertRedirect('https://checkout.stripe.test/cs_up');
    Livewire::test(PackChangePanel::class, ['submission' => $dossier])->call('pay')->assertRedirect('https://checkout.stripe.test/cs_up');

    $payment = Payment::where('type', PaymentType::PackUpgrade)->sole();
    expect($payment->amount_cents)->toBe(21675)
        ->and($payment->service_year)->toBe(2026)
        ->and(PackChange::sole()->payment_id)->toBe($payment->id);
    Http::assertSent(fn ($req) => str_contains(urldecode($req->body()), 'Festilaw Pro Pack upgrade 2026'));
});

it('switches the dossier to Pro once the difference is paid, once, and confirms it to both parties', function () {
    $dossier = activeCreator();
    $creatorMandate = $dossier->contract;
    $payment = upgradeAwaitingPayment($dossier);

    app(MarkPaymentSucceededAction::class)->execute($payment, 'cs_up');
    app(MarkPaymentSucceededAction::class)->execute($payment->fresh(), 'cs_up'); // webhook rejoue

    $dossier->refresh();
    expect($dossier->type)->toBe(SubmissionType::Pro)
        ->and($dossier->contract->pack)->toBe(SubmissionType::Pro)
        ->and($creatorMandate->fresh()->role)->toBe(ContractRole::Superseded)
        ->and(PackChange::sole()->status)->toBe(PackChangeStatus::Completed)
        ->and($dossier->isActive())->toBeTrue()
        ->and($dossier->type->annualCents())->toBe(120000); // le renouvellement de janvier sera au tarif Pro

    Mail::assertSent(PackUpgradeConfirmed::class, 1);
    Mail::assertSent(PackUpgradeConfirmed::class, fn (PackUpgradeConfirmed $mail) => $mail->hasTo('creator@example.com')
        && str_contains($mail->render(), '€216.75')
        && str_contains($mail->render(), '€1,200'));
    Mail::assertSent(FunnelNotification::class, fn (FunnelNotification $mail) => $mail->reason === FunnelNotificationReason::PackUpgraded);
    Mail::assertNotSent(FunnelNotification::class, fn (FunnelNotification $mail) => $mail->reason === FunnelNotificationReason::PaymentReceived);
});

it('confirms the switch on the return from Stripe and welcomes the client to the Pro Pack', function () {
    Http::fake(['*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_up', 'status' => 'complete', 'payment_status' => 'paid'])]);
    $dossier = activeCreator();
    upgradeAwaitingPayment($dossier);

    get(route('my-project', ['dossier' => 'creatortok', 'upgrade_return' => 1]))
        ->assertOk()
        ->assertSee('You&#039;re now on the Pro Pack', false)
        ->assertSee('Pro Pack');

    expect($dossier->fresh()->type)->toBe(SubmissionType::Pro);
});

it('lets the client keep the Creator pack before paying', function () {
    $dossier = activeCreator();
    Livewire::test(PackChangePanel::class, ['submission' => $dossier])
        ->call('startUpgrade')
        ->call('cancelUpgrade')
        ->assertSee('No change: you keep the Creator Pack.');

    $upgrade = PackChange::sole();
    expect($upgrade->status)->toBe(PackChangeStatus::Cancelled)
        ->and($upgrade->contract->role)->toBe(ContractRole::Superseded)
        ->and($dossier->fresh()->type)->toBe(SubmissionType::Starter)
        ->and(app(PackChangeService::class)->canStartUpgrade($dossier->fresh()))->toBeTrue();
});

it('still applies a switch abandoned by the client but paid anyway (the money received prevails)', function () {
    $dossier = activeCreator();
    $payment = upgradeAwaitingPayment($dossier);
    PackChange::sole()->update(['status' => PackChangeStatus::Cancelled]);

    app(MarkPaymentSucceededAction::class)->execute($payment, 'cs_up');

    expect($dossier->fresh()->type)->toBe(SubmissionType::Pro)
        ->and(PackChange::sole()->status)->toBe(PackChangeStatus::Completed);
});

it('lets Festilaw cancel a paid switch from the back-office: refund, then back to Creator', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_up', 'payment_intent' => 'pi_up']),
        '*/v1/refunds' => Http::response(['id' => 're_up', 'status' => 'succeeded']),
    ]);
    $dossier = activeCreator();
    $creatorMandate = $dossier->contract;
    $payment = upgradeAwaitingPayment($dossier);
    app(MarkPaymentSucceededAction::class)->execute($payment, 'cs_up');
    $upgrade = PackChange::sole();
    actingAs($admin = User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->assertSee('Annuler le passage au Pro et rembourser')
        ->call('revertPackUpgrade', $upgrade->id)
        ->assertDispatched('admin-toast', type: 'success');

    $dossier->refresh();
    expect($dossier->type)->toBe(SubmissionType::Starter)
        ->and($dossier->contract->id)->toBe($creatorMandate->id)
        ->and($upgrade->fresh()->status)->toBe(PackChangeStatus::Reverted)
        ->and($upgrade->fresh()->decided_by)->toBe($admin->id)
        ->and($upgrade->contract->fresh()->role)->toBe(ContractRole::Superseded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($dossier->isActive())->toBeTrue();
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v1/refunds') && $req->hasHeader('Idempotency-Key', 'refund-'.$payment->id));
});

it('changes nothing when the refund fails at Stripe', function () {
    Http::fake([
        '*/v1/checkout/sessions/*' => Http::response(['id' => 'cs_up', 'payment_intent' => 'pi_up']),
        '*/v1/refunds' => Http::response(['error' => ['message' => 'nope']], 400),
    ]);
    $dossier = activeCreator();
    $payment = upgradeAwaitingPayment($dossier);
    app(MarkPaymentSucceededAction::class)->execute($payment, 'cs_up');
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->call('revertPackUpgrade', PackChange::sole()->id)
        ->assertDispatched('admin-toast', type: 'error');

    expect($dossier->fresh()->type)->toBe(SubmissionType::Pro)
        ->and(PackChange::sole()->status)->toBe(PackChangeStatus::Completed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});
