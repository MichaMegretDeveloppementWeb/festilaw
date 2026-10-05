<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\ScaleAuditConfirmed;
use App\Mail\StarterPaymentConfirmed;
use App\Models\Contract;
use App\Models\Submission;
use App\Repositories\SettingRepository;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', ['api_key' => 'testkey', 'api_base_url' => 'https://www.signwell.com/api/v1', 'test_mode' => true]);
    // Les gateways sont des singletons lus a la construction : les oublier apres avoir pose la config.
    app()->forgetInstance(PaymentGatewayRegistry::class);
    app()->forgetInstance(StripePaymentGateway::class);
    app()->forgetInstance(SignatureGatewayInterface::class);
    // SignWell signe ses webhooks avec l'id du webhook : celui memorise par festilaw:signwell-webhook.
    app(SettingRepository::class)->put('signwell.webhook_ids', json_encode(['hook_test_1']));
    Mail::fake();
});

/** POSTs a Stripe-signed payment webhook (body signed with the test webhook secret). */
function postStripeWebhook(array $payload): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$body}", 'whsec_x');

    return test()->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $body);
}

/** POSTs a SignWell-signed signature webhook (HMAC over "{type}@{time}" keyed by the webhook id). */
function postSignwellWebhook(string $type, string $documentId, string $status, string $webhookId = 'hook_test_1'): TestResponse
{
    $time = 1689332249;
    $hash = hash_hmac('sha256', "{$type}@{$time}", $webhookId);

    return postJson('/webhooks/signature', [
        'event' => ['type' => $type, 'time' => $time, 'hash' => $hash],
        'data' => ['object' => ['id' => $documentId, 'status' => $status]],
    ]);
}

it('confirms a payment from a Stripe webhook and emails the buyer in their locale', function () {
    // La locale du client est portee par le dossier : le webhook n'a aucun contexte de locale,
    // l'email de confirmation doit quand meme partir dans la langue du client.
    $submission = Submission::factory()->starter()->create(['locale' => 'fr']);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Pending,
    ]);

    postStripeWebhook([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid']],
    ])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Paid);

    Mail::assertSent(
        StarterPaymentConfirmed::class,
        fn (StarterPaymentConfirmed $mail) => $mail->hasTo($submission->email) && $mail->locale === 'fr',
    );
});

it('confirms a SCALE audit from a Stripe webhook, advancing the dossier to in-progress (not the subscription "paid")', function () {
    // Le webhook signe est la source de verite en prod : on verifie que l'audit SCALE y est bien traite
    // comme un audit (statut "en cours", pas "Paye" d'abonnement) et declenche l'email dedie.
    $submission = Submission::factory()->scale()->create([
        'resume_token' => 'scaletok', 'resume_expires_at' => now()->addDays(30), 'email' => 'bigco@example.com',
    ]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::ScaleAudit,
        'amount_cents' => 7500,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_scale_wh',
        'status' => PaymentStatus::Pending,
    ]);

    postStripeWebhook([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_scale_wh', 'payment_status' => 'paid']],
    ])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->fresh()->isActive())->toBeFalse();

    Mail::assertSent(ScaleAuditConfirmed::class, fn (ScaleAuditConfirmed $mail) => $mail->hasTo('bigco@example.com'));
    Mail::assertNotSent(StarterPaymentConfirmed::class);
});

it('confirms a payment from a valid Stripe webhook', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create();
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Pending,
    ]);

    // Le corps doit etre transmis brut : la signature Stripe porte sur ces octets exacts.
    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid']],
    ]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    $this->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('marks a payment failed from a Stripe async_payment_failed webhook, leaving the dossier payable', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = json_encode([
        'type' => 'checkout.session.async_payment_failed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'unpaid']],
    ]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    $this->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);
});

it('marks a payment processing from a completed-but-unpaid Stripe webhook (async method)', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'unpaid']],
    ]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    $this->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload)->assertNoContent();

    // Argent pas encore capture : le paiement passe "en cours", le dossier reste payable.
    expect($payment->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingPayment);
});

it('marks a succeeded payment refunded from a Stripe charge.refunded webhook', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    // L'objet d'un charge.refunded est une Charge : on rapproche par notre payment_id (metadata).
    // Remboursement INTEGRAL (refunded=true) : desactive le dossier (un partiel ne coupe rien).
    $payload = json_encode([
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'refunded' => true, 'amount' => 33300, 'amount_refunded' => 33300, 'metadata' => ['payment_id' => (string) $payment->id]]],
    ]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    $this->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

/** Un charge.dispute.closed perdu tel que Stripe l'envoie vraiment (metadata vides), signe. */
function postLostStripeDispute(): TestResponse
{
    $payload = json_encode([
        'type' => 'charge.dispute.closed',
        'data' => ['object' => ['id' => 'du_1', 'object' => 'dispute', 'status' => 'lost', 'metadata' => new stdClass, 'charge' => 'ch_1', 'payment_intent' => 'pi_1']],
    ]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    return test()->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload);
}

it('deactivates the dossier when a dispute is lost, from a dispute event as Stripe really sends it', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid, 'resume_expires_at' => null]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'service_year' => (int) now()->year,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);
    expect($submission->fresh()->isActive())->toBeTrue();

    // Le Dispute n'a pas nos metadata : le site lit notre id sur son PaymentIntent.
    Http::fake(['*/v1/payment_intents/pi_1' => Http::response(['id' => 'pi_1', 'metadata' => ['payment_id' => (string) $payment->id]])]);

    postLostStripeDispute()->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($submission->fresh()->isActive())->toBeFalse();
});

it('answers 400 to a lost dispute Stripe cannot trace yet, so Stripe sends it again, and changes nothing', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'provider_reference' => 'cs_1',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);
    Http::fake(['*/v1/payment_intents/*' => Http::response(['error' => ['message' => 'down']], 500)]);

    postLostStripeDispute()->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('reconciles a Stripe webhook by our payment id when the provider reference does not match', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);

    $submission = Submission::factory()->starter()->create();
    // provider_reference jamais stocke (echec rare apres createCheckout) : on rapproche par notre id.
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 33300,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_unknown', 'payment_status' => 'paid', 'client_reference_id' => (string) $payment->id]],
    ]);
    $time = now()->timestamp;
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x');

    $this->call('POST', '/webhooks/payment/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
    ], $payload)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Paid);
});

it('marks a contract declined from a SignWell webhook, leaving the dossier signable', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress]);
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC_DEC',
    ]);

    postSignwellWebhook('document_declined', 'DOC_DEC', 'Declined')->assertNoContent();

    // Plus jamais bloque en "en attente" : refus persiste, le dossier reste au stade signature.
    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Declined)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});

it('marks a contract expired from a SignWell webhook', function () {
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress]);
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC_EXP',
    ]);

    postSignwellWebhook('document_expired', 'DOC_EXP', 'Expired')->assertNoContent();

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Expired);
});

it('marks a contract signed from a valid SignWell webhook', function () {
    Storage::fake('local');
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => true,
    ]);
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF', 200)]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    $time = 1689332249;
    $hash = hash_hmac('sha256', "document_completed@{$time}", 'hook_test_1');

    postJson('/webhooks/signature', [
        'event' => ['type' => 'document_completed', 'time' => $time, 'hash' => $hash],
        'data' => ['object' => ['id' => 'DOC1', 'status' => 'Completed']],
    ])->assertNoContent();

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Signed)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
});

it('rejects a SignWell webhook with an invalid signature and leaves the contract untouched', function () {
    Http::fake(['*/api/v1/hooks' => Http::response([['id' => 'hook_test_1', 'callback_url' => route('webhooks.signature')]])]);
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => true,
    ]);

    $submission = Submission::factory()->starter()->create();
    $contract = Contract::factory()->for($submission)->create([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
    ]);

    postJson('/webhooks/signature', [
        'event' => ['type' => 'document_completed', 'time' => 1689332249, 'hash' => 'not-a-valid-hash'],
        'data' => ['object' => ['id' => 'DOC1', 'status' => 'Completed']],
    ])->assertStatus(400);

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Pending)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::InProgress);
});

it('asks the provider to retry a paid webhook whose payment is not (yet) known', function () {
    // Course creation/webhook : notre ligne peut ne pas exister encore. On repond 500 pour que le
    // provider reessaie plus tard, plutot que de perdre silencieusement une confirmation de paiement.
    postStripeWebhook([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_unknown', 'payment_status' => 'paid']],
    ])->assertStatus(500);
});

it('acknowledges a non-actionable webhook for an unknown reference without failing', function () {
    // Evenement non actionnable (type non gere) pour une reference inconnue : on acquitte (204).
    postStripeWebhook([
        'type' => 'payment_intent.created',
        'data' => ['object' => ['id' => 'pi_unknown']],
    ])->assertNoContent();
});

it('returns 400 when the targeted payment provider is not enabled', function () {
    config()->set('payment.enabled', []); // stripe absent du registre

    postJson('/webhooks/payment/stripe', ['provider_reference' => 'x'])
        ->assertStatus(400);
});

it('returns 400 when the payment webhook cannot be verified', function () {
    // Stripe active mais sans secret de webhook : la verification echoue.
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe.webhook_secret', null);

    postJson('/webhooks/payment/stripe', ['provider_reference' => 'x'])
        ->assertStatus(400);
});

/** A SignWell contract, already confirmed as signed by the browser, whose signed PDF is still missing. */
function signwellContractSignedWithoutPdf(): Contract
{
    return Contract::factory()->for(Submission::factory()->starter()->create(['status' => SubmissionStatus::AwaitingPayment]))->create([
        'signature_status' => SignatureStatus::Signed,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC_PDF',
        'signed_file_path' => null,
        'signed_at' => now(),
    ]);
}

it('rejects a SignWell webhook signed with the API key (SignWell keys the hash with the webhook id)', function () {
    Http::fake(['*/api/v1/hooks' => Http::response([['id' => 'hook_test_1', 'callback_url' => route('webhooks.signature')]])]);
    $contract = signwellContractSignedWithoutPdf();
    $contract->update(['signature_status' => SignatureStatus::Pending, 'signed_at' => null]);

    // Ancien calcul (cle API) : c'est lui qui faisait rejeter tous les webhooks en production.
    postSignwellWebhook('document_completed', 'DOC_PDF', 'Completed', webhookId: 'testkey')->assertStatus(400);

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Pending);
});

it('re-reads the registered webhook ids once when none is known, then accepts the event', function () {
    Storage::fake('local');
    app(SettingRepository::class)->put('signwell.webhook_ids', json_encode([]));
    Http::fake([
        '*/api/v1/hooks' => Http::response([
            ['id' => 'hook_other', 'callback_url' => 'https://another-site.example/webhooks/signature'],
            ['id' => 'hook_from_ui', 'callback_url' => route('webhooks.signature')],
        ]),
        '*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF', 200),
    ]);
    signwellContractSignedWithoutPdf();

    postSignwellWebhook('document_completed', 'DOC_PDF', 'Completed', webhookId: 'hook_from_ui')->assertNoContent();

    // L'id du webhook de notre URL est memorise (pas celui d'un autre site).
    expect(json_decode(app(SettingRepository::class)->get('signwell.webhook_ids'), true))->toBe(['hook_from_ui']);

    // Un webhook invalide ensuite ne relit pas la liste avant le delai (pas de martelage de l'API).
    postSignwellWebhook('document_completed', 'DOC_PDF', 'Completed', webhookId: 'forged')->assertStatus(400);
    Http::assertSentCount(2); // 1 lecture de la liste + 1 telechargement du PDF
});

it('refuses every webhook while no SignWell webhook is registered for the site', function () {
    app(SettingRepository::class)->put('signwell.webhook_ids', json_encode([]));
    Http::fake(['*/api/v1/hooks' => Http::response([])]);

    postSignwellWebhook('document_completed', 'DOC_PDF', 'Completed', webhookId: 'anything')->assertStatus(400);
});

it('fetches the signed PDF on a completed webhook even when the browser already confirmed the signature', function () {
    Storage::fake('local');
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF', 200)]);
    $contract = signwellContractSignedWithoutPdf();

    postSignwellWebhook('document_completed', 'DOC_PDF', 'Completed')->assertNoContent();

    expect($contract->fresh()->signed_file_path)->toBe('contracts/DOC_PDF.pdf')
        ->and($contract->fresh()->submission->status)->toBe(SubmissionStatus::AwaitingPayment); // statut inchange
    Storage::disk('local')->assertExists('contracts/DOC_PDF.pdf');
});

it('acknowledges the webhook when the signed PDF is not generated yet (the minute backfill catches it)', function () {
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('', 404)]);
    $contract = signwellContractSignedWithoutPdf();

    postSignwellWebhook('document_completed', 'DOC_PDF', 'Completed')->assertNoContent();

    expect($contract->fresh()->signed_file_path)->toBeNull()
        ->and($contract->fresh()->signature_status)->toBe(SignatureStatus::Signed);
});
