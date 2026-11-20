<?php

use App\Actions\Web\Payment\MarkPaymentExpiredAction;
use App\Actions\Web\Payment\MarkPaymentFailedAction;
use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Analytics\AudienceEvent;
use App\Enums\Contract\SignatureEventOutcome;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Livewire\Web\Contact\ContactForm;
use App\Livewire\Web\Funnel\AccessFileForm;
use App\Livewire\Web\Funnel\ScaleForm;
use App\Livewire\Web\Funnel\StarterForm;
use App\Livewire\Web\Funnel\StarterJourney;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Analytics\FalconAudienceTracker;
use App\Services\Payment\PaymentReturnService;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FakeAudienceTracker;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/*
 | Mesure d'audience (plan SC8 du 20/11) : les evenements declares, les boutons qui les portent, et les faits
 | du parcours enregistres une seule fois, dans la requete du visiteur, quand ils sont acquis. Un paiement se
 | compte a l'encaissement, dans la session de mesure gardee au clic sur « payer » (decision du 20/11).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('festilaw.starter.required_documents', ['turnover_proof', 'technical_documentation']);
    config()->set('festilaw.pro.required_documents', ['turnover_proof', 'technical_documentation']);
    app()->forgetInstance(SignatureGatewayInterface::class);
    Mail::fake();
    $this->audience = FakeAudienceTracker::bind();
});

/** Un dossier Creator neuf, ses pieces deposees : le parcours en est a la signature. */
function audienceDossierAtSignature(): Submission
{
    $submission = app(CreateStarterSubmissionAction::class)->execute(['company_name' => 'Wildthread Ceramics', 'first_name' => 'Maya', 'email' => 'maya@example.com'])->submission;

    foreach ($submission->type->requiredDocuments() as $type) {
        $submission->uploadedDocuments()->create([
            'type' => $type,
            'file_path' => "starter-documents/{$submission->reference}/{$type->value}.pdf",
            'original_filename' => "{$type->value}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
            'uploaded_at' => now(),
        ]);
    }

    $submission->contract->update(['signature_provider' => 'stub', 'signature_provider_reference' => 'ref-1']);

    return $submission->fresh();
}

/** Un prestataire de signature qui repond « signe » ou « en attente ». */
function bindAudienceSignatureProvider(bool $signed): void
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
            return new SigningSessionData('ref-1', 'https://example.com/sign');
        }

        public function currentSigningUrl(Contract $contract): ?string
        {
            return $this->signed ? null : 'https://example.com/sign';
        }

        public function checkStatus(Contract $contract): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', $this->signed ? SignatureEventOutcome::Signed : SignatureEventOutcome::Unresolved);
        }

        public function parseWebhook(Request $request): SignatureWebhookData
        {
            return new SignatureWebhookData('ref-1', SignatureEventOutcome::Unresolved);
        }

        public function downloadSignedDocument(Contract $contract): ?string
        {
            return null;
        }
    });
}

/** Un paiement en attente chez Stripe, lance au clic sur « payer » par un client mesure dans la session donnee. */
function audiencePendingPayment(string $pack, PaymentType $type, ?string $session = 'visit-ref'): Payment
{
    $submission = Submission::factory()->{$pack}()->create(['resume_token' => Str::random(40), 'resume_expires_at' => now()->addDays(30)]);

    return $submission->payments()->create([
        'type' => $type, 'amount_cents' => 1000, 'service_year' => 2026, 'currency' => 'EUR', 'provider' => 'stripe',
        'status' => PaymentStatus::Pending, 'analytics_reference' => $session,
    ]);
}

/** Un paiement en attente chez Stripe, et ses adresses de retour signees (reussite, annulation). */
function audiencePaymentReturnUrls(string $pack, PaymentType $type): array
{
    $submission = Submission::factory()->{$pack}()->create(['resume_token' => Str::random(40), 'resume_expires_at' => now()->addDays(30)]);
    $payment = $submission->payments()->create(['type' => $type, 'amount_cents' => 1000, 'currency' => 'EUR', 'provider' => 'stripe', 'status' => PaymentStatus::Pending]);

    return app(PaymentReturnService::class)->returnUrls($payment);
}

it('declares every event of the site with its label, its points and whether it converts', function () {
    $registry = app(EventRegistry::class);

    expect($registry->failure())->toBeNull()
        ->and($registry->names())->toEqualCanonicalizing(array_column(AudienceEvent::cases(), 'value'));

    foreach (AudienceEvent::cases() as $event) {
        $declared = $registry->get($event->value);

        expect($declared->label)->toBe($event->label())
            ->and($declared->value)->toBe($event->points())
            ->and($declared->isConversion())->toBe($event->isConversion());
    }
});

it('scores only the money, at one point for ten euros of yearly catalogue value', function () {
    $scored = collect(AudienceEvent::cases())->filter(fn (AudienceEvent $event): bool => $event->points() !== null)
        ->mapWithKeys(fn (AudienceEvent $event): array => [$event->value => $event->points()])->all();
    $conversions = collect(AudienceEvent::cases())->filter->isConversion()->map->value->values()->all();

    expect($scored)->toBe([
        'creator.paid' => 33, 'pro.paid' => 120, 'upgrade.paid' => 87,
        'renewal.creator.paid' => 33, 'renewal.pro.paid' => 120, 'scale.audit_paid' => 8,
    ])
        ->and($conversions)->toEqualCanonicalizing([
            'creator.opened', 'pro.opened', 'scale.requested', 'scale.booked', 'contact.sent',
            'creator.paid', 'pro.paid', 'upgrade.paid', 'renewal.creator.paid', 'renewal.pro.paid', 'scale.audit_paid',
        ]);
});

it('names every event plainly and once, in at most 45 characters, without a colon or a middle dot', function () {
    $labels = array_map(fn (AudienceEvent $event): string => $event->label(), AudienceEvent::cases());

    expect(array_unique($labels))->toHaveCount(count($labels));

    foreach ($labels as $label) {
        expect(mb_strlen($label))->toBeLessThanOrEqual(45)
            ->and($label)->not->toContain(':')
            ->and($label)->not->toContain('·');
    }
});

it('puts on the buttons of the site exactly the clicks it declares', function () {
    $used = collect(File::allFiles(resource_path('views')))
        ->flatMap(fn ($file): array => preg_match_all('/data-track-event="([^"]+)"/', $file->getContents(), $matches) ? $matches[1] : [])
        ->unique()->values()->all();

    // Les douze clics nommes ; les faits viennent du serveur, jamais d'un attribut.
    expect($used)->toEqualCanonicalizing([
        'header.to_offers', 'home.to_offers', 'closing.to_offers',
        'offer.creator.chosen', 'offer.pro.chosen', 'offer.scale.chosen',
        'home.to_quiz', 'quiz.answered', 'article.to_quiz', 'article.to_pricing',
        'signature.opened', 'payment.opened',
    ]);
});

it('builds every funnel step from a declared event or an existing route', function () {
    $funnels = app(FunnelRegistry::class);
    $events = app(EventRegistry::class)->names();

    expect($funnels->failure())->toBeNull()
        ->and(array_map(fn ($funnel): string => $funnel->key, $funnels->all()))->toBe([
            'subscription_creator', 'subscription_pro', 'scale_request', 'offers_to_dossier', 'quiz_to_dossier', 'blog_to_dossier',
        ]);

    foreach ($funnels->all() as $funnel) {
        foreach ($funnel->steps() as $step) {
            foreach ($step->branches === [] ? [$step] : $step->branches as $target) {
                expect($target->event === null ? Route::has((string) $target->route) : in_array($target->event, $events, true))->toBeTrue();
            }
        }
    }
});

it('records a fact once per visitor and key, through Falcon Analytics', function () {
    // La classe du package est finale : un espion prend sa place derriere la facade.
    $spy = new class
    {
        /** @var list<string> */
        public array $names = [];

        /** @var list<?string> */
        public array $sessions = [];

        public function record(string $name, ?int $value = null, array $props = [], ?string $session = null): void
        {
            $this->names[] = $name;
            $this->sessions[] = $session;
        }

        public function currentSession(): ?string
        {
            return 'sealed-visit';
        }
    };
    Analytics::swap($spy);
    $tracker = app(FalconAudienceTracker::class);

    $tracker->recordOnce(AudienceEvent::CreatorPaid, 'payment:7');
    $tracker->recordOnce(AudienceEvent::CreatorPaid, 'payment:7');
    $tracker->recordOnce(AudienceEvent::CreatorPaid, 'payment:8');
    // Un paiement confirme plus tard passe la reference de la visite du client (Falcon Analytics 599edd0).
    $tracker->record(AudienceEvent::ProPaid, 'kept-visit');

    expect($spy->names)->toBe(['creator.paid', 'creator.paid', 'pro.paid'])
        ->and($spy->sessions)->toBe([null, null, 'kept-visit'])
        ->and($tracker->currentSession())->toBe('sealed-visit');
});

it('measures the public pages of a visitor, never those of a signed-in account', function () {
    expect(get('/')->assertOk()->getContent())->toContain('vendor/falcon/analytics/analytics.js')
        ->toContain('data-track-event="header.to_offers"')
        ->toContain('data-track-event="home.to_offers"')
        ->toContain('data-track-event="offer.pro.chosen"')
        ->toContain('data-track-event="quiz.answered"')
        ->toContain('data-track-label="Menu, Tarifs"');

    actingAs(User::factory()->create());

    expect(get('/')->assertOk()->getContent())->not->toContain('vendor/falcon/analytics/analytics.js')
        ->and(get('/admin/submissions')->assertOk()->getContent())->not->toContain('vendor/falcon/analytics/analytics.js');
});

it('counts a new Creator or Pro dossier, and a link asked again for an existing one', function (string $type, string $opened) {
    $submit = fn () => Livewire::test(StarterForm::class, ['type' => $type])
        ->set('company_name', 'Wildthread')->set('first_name', 'Maya')->set('last_name', 'Thornton')
        ->set('email', 'maya@example.com')->call('submit');

    $submit();
    $submit();

    expect($this->audience->recorded)->toBe([$opened, 'dossier.link_requested']);
})->with([
    'creator' => ['starter', 'creator.opened'],
    'pro' => ['pro', 'pro.opened'],
]);

it('opens the new dossier page and the Scale space with a full page load, so the collector counts them', function () {
    // Jusqu'a Falcon Analytics 599edd0, le collecteur ne comptait pas une page ouverte par navigation Livewire
    // (essai du 20/11) : ces deux pages se chargent en entier, ce que ce test garde.
    $starter = Livewire::test(StarterForm::class, ['type' => 'starter'])
        ->set('company_name', 'Wildthread')->set('first_name', 'Maya')->set('last_name', 'Thornton')
        ->set('email', 'maya@example.com')->call('submit')
        ->assertRedirectContains('/get-started/starter/');
    $scale = Livewire::test(ScaleForm::class)
        ->set('company_name', 'Bigco')->set('first_name', 'Dana')->set('last_name', 'Rivera')
        ->set('email', 'bigco@example.com')->call('submit')
        ->assertRedirectContains('/get-started/scale/');

    expect($starter->effects)->not->toHaveKey('redirectUsingNavigate')
        ->and($scale->effects)->not->toHaveKey('redirectUsingNavigate');
});

it('counts nothing for a form rejected as spam or that does not validate', function () {
    Livewire::test(StarterForm::class)->set('hp', 'bot')->set('company_name', 'Spam')->set('email', 'spam@example.com')->call('submit');
    Livewire::test(StarterForm::class)->set('email', 'not-an-email')->call('submit')->assertHasErrors();
    Livewire::test(ContactForm::class)->set('hp', 'bot')->call('save');
    Livewire::test(ScaleForm::class)->set('email', '')->call('submit')->assertHasErrors();

    expect($this->audience->recorded)->toBe([]);
});

it('counts a dossier link only when one is really sent', function () {
    Livewire::test(AccessFileForm::class)->set('email', 'nobody@example.com')->call('submit');
    expect($this->audience->recorded)->toBe([]);

    Submission::factory()->starter()->create(['email' => 'maya@example.com', 'status' => SubmissionStatus::InProgress, 'resume_expires_at' => now()->addDays(30)]);
    Livewire::test(AccessFileForm::class)->set('email', 'maya@example.com')->call('submit');

    expect($this->audience->recorded)->toBe(['dossier.link_requested']);
});

it('counts a Scale request, a contact message and each quiz result', function () {
    Livewire::test(ScaleForm::class)->set('company_name', 'Bigco')->set('first_name', 'Dana')->set('last_name', 'Rivera')->set('email', 'bigco@example.com')->call('submit');
    Livewire::test(ContactForm::class)->set('name', 'Maya Thornton')->set('email', 'maya@example.com')->set('message', 'We ship handmade ceramics to the EU and need help.')->call('save');
    postJson(route('quiz.result'), ['q1_based_outside_eu' => true, 'q2_sells_to_eu' => true, 'q3_sells_restricted' => false])->assertCreated();
    postJson(route('quiz.result'), ['q1_based_outside_eu' => true, 'q2_sells_to_eu' => true, 'q3_sells_restricted' => true])->assertCreated();
    postJson(route('quiz.result'), ['q1_based_outside_eu' => false, 'q2_sells_to_eu' => true, 'q3_sells_restricted' => false])->assertCreated();

    expect($this->audience->recorded)->toBe(['scale.requested', 'contact.sent', 'quiz.concerned', 'quiz.excluded', 'quiz.not_concerned']);
});

it('counts the first Scale booking only', function () {
    $dossier = Submission::factory()->scale()->create(['status' => SubmissionStatus::New, 'resume_token' => 'scaletok', 'resume_expires_at' => now()->addDays(30)]);
    Payment::factory()->succeeded()->for($dossier)->create(['type' => PaymentType::ScaleAudit, 'provider_reference' => 'cs_scale']);

    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertRedirect();
    post(route('get-started.scale.book', ['dossier' => 'scaletok']))->assertRedirect();

    expect($this->audience->recorded)->toBe(['scale.booked']);
});

it('counts the documents sent from the journey', function () {
    Storage::fake('local');
    $submission = app(CreateStarterSubmissionAction::class)->execute(['company_name' => 'Wildthread Ceramics', 'first_name' => 'Maya', 'email' => 'maya@example.com'])->submission;

    Livewire::test(StarterJourney::class, ['submission' => $submission])
        ->set('documents.turnover_proof', UploadedFile::fake()->create('turnover.pdf', 120, 'application/pdf'))
        ->set('documents.technical_documentation', UploadedFile::fake()->create('tech.pdf', 120, 'application/pdf'))
        ->call('submitDocuments')
        ->assertHasNoErrors();

    expect($this->audience->recorded)->toBe(['dossier.documents_sent']);
});

it('counts the signature once when the page sees it, whoever confirmed it first', function () {
    bindAudienceSignatureProvider(signed: true);

    // Le visiteur confirme lui-meme, puis clique une seconde fois.
    Livewire::test(StarterJourney::class, ['submission' => audienceDossierAtSignature()])
        ->call('confirmSignature')
        ->call('confirmSignature');

    expect($this->audience->recorded)->toBe(['dossier.contract_signed']);
});

it('counts the signature the webhook confirmed before the page, and not again after a reload', function () {
    bindAudienceSignatureProvider(signed: true);
    $submission = audienceDossierAtSignature();
    $page = Livewire::test(StarterJourney::class, ['submission' => $submission]);

    // Le webhook de SignWell passe avant le prochain appel de la page.
    $submission->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => now()]);
    $page->call('autoConfirmSignature');

    // Rechargement : la page s'ouvre sur un contrat deja signe.
    Livewire::test(StarterJourney::class, ['submission' => $submission->fresh()])->call('autoConfirmSignature');

    expect($this->audience->recorded)->toBe(['dossier.contract_signed']);
});

it('counts a pack switched before payment', function () {
    $submission = audienceDossierAtSignature();

    Livewire::test(StarterJourney::class, ['submission' => $submission])->call('changePack')->assertHasNoErrors();

    expect($this->audience->recorded)->toBe(['dossier.pack_switched']);
});

it('counts a payment once the money is in, by its kind, in the session kept when the client clicked pay', function (string $pack, PaymentType $type, string $paid) {
    $payment = audiencePendingPayment($pack, $type);

    // Le webhook redelivre le meme evenement : la seconde confirmation ne compte pas.
    app(MarkPaymentSucceededAction::class)->execute($payment, 'cs_paid');
    app(MarkPaymentSucceededAction::class)->execute($payment->fresh(), 'cs_paid');

    expect($this->audience->sessions)->toBe([['event' => $paid, 'session' => 'visit-ref']])
        ->and($payment->fresh()->analytics_reference)->toBeNull();
})->with([
    'creator, first year' => ['starter', PaymentType::StarterSubscription, 'creator.paid'],
    'pro, first year' => ['pro', PaymentType::StarterSubscription, 'pro.paid'],
    'creator renewal' => ['starter', PaymentType::AnnualRenewal, 'renewal.creator.paid'],
    'pro renewal' => ['pro', PaymentType::AnnualRenewal, 'renewal.pro.paid'],
    'switch to pro' => ['starter', PaymentType::PackUpgrade, 'upgrade.paid'],
    'scale audit' => ['scale', PaymentType::ScaleAudit, 'scale.audit_paid'],
]);

it('no longer counts a payment on the return from Stripe, which may come before the money is in', function () {
    [$successUrl] = audiencePaymentReturnUrls('starter', PaymentType::StarterSubscription);

    get($successUrl)->assertRedirect();

    expect($this->audience->recorded)->toBe([]);
});

it('keeps the visit of a buyer back from Stripe on a payment still pending, the last one winning, and never on a settled one', function () {
    $pending = audiencePendingPayment('starter', PaymentType::StarterSubscription, 'click-visit');
    $settled = audiencePendingPayment('starter', PaymentType::StarterSubscription, null);
    $settled->update(['status' => PaymentStatus::Succeeded]);
    $unmeasured = audiencePendingPayment('scale', PaymentType::ScaleAudit, 'click-visit');
    $this->audience->currentSession = 'return-visit';

    get(app(PaymentReturnService::class)->returnUrls($pending)[0])->assertRedirect();
    get(app(PaymentReturnService::class)->returnUrls($settled)[0])->assertRedirect();
    get(app(PaymentReturnService::class)->returnUrls($pending)[1])->assertRedirect();
    // Un retour sans visite mesuree garde la reference prise au clic.
    $this->audience->currentSession = null;
    get(app(PaymentReturnService::class)->returnUrls($unmeasured)[0])->assertRedirect();

    expect($pending->fresh()->analytics_reference)->toBe('return-visit')
        ->and($settled->fresh()->analytics_reference)->toBeNull()
        ->and($unmeasured->fresh()->analytics_reference)->toBe('click-visit');
});

it('counts neither a double payment nor a payment started without an audience session', function () {
    $unmeasured = audiencePendingPayment('starter', PaymentType::StarterSubscription, session: null);
    $double = audiencePendingPayment('starter', PaymentType::StarterSubscription);
    $double->submission->payments()->create([
        'type' => PaymentType::StarterSubscription, 'amount_cents' => 1000, 'service_year' => 2026, 'currency' => 'EUR',
        'provider' => 'stripe', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(),
    ]);

    app(MarkPaymentSucceededAction::class)->execute($unmeasured, 'cs_unmeasured');
    app(MarkPaymentSucceededAction::class)->execute($double, 'cs_double');

    expect($this->audience->recorded)->toBe([])
        ->and($unmeasured->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($double->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('drops the audience session of a payment that fails or expires', function () {
    $failed = audiencePendingPayment('starter', PaymentType::StarterSubscription);
    $expired = audiencePendingPayment('scale', PaymentType::ScaleAudit);

    app(MarkPaymentFailedAction::class)->execute($failed);
    app(MarkPaymentExpiredAction::class)->execute($expired);

    expect($failed->fresh()->analytics_reference)->toBeNull()
        ->and($expired->fresh()->analytics_reference)->toBeNull()
        ->and($this->audience->recorded)->toBe([]);
});

it('counts a payment abandoned at Stripe', function () {
    [, $cancelUrl] = audiencePaymentReturnUrls('starter', PaymentType::StarterSubscription);

    get($cancelUrl)->assertRedirect();

    expect($this->audience->recorded)->toBe(['payment.cancelled']);
});

it('tells visitors about the audience measurement in the privacy policy, in each language', function (string $path, string $title, string $retention) {
    get($path)->assertOk()->assertSee($title, false)->assertSee($retention, false);
})->with([
    'english' => ['/privacy-policy', 'Audience measurement.', 'Audience measurement data: 180 days'],
    'french' => ['/fr/privacy-policy', "Mesure d'audience.", "Données de mesure d'audience : 180 jours"],
    'spanish' => ['/es/privacy-policy', 'Medición de audiencia.', 'Datos de medición de audiencia: 180 días'],
]);
