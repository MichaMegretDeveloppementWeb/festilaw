<?php

use App\Actions\Web\Starter\ChangeStarterPackAction;
use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Starter\StarterException;
use App\Livewire\Web\Funnel\StarterJourney;
use App\Mail\FunnelNotification;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/*
 | Changement de pack avant paiement, depuis le dossier : le dossier est annule et remplace par un nouveau
 | au pack choisi (infos, infos du mandat et pieces repris, mandat a re-signer), le visiteur y est emmene.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('festilaw.starter.required_documents', ['turnover_proof', 'technical_documentation']);
    config()->set('festilaw.pro.required_documents', ['turnover_proof', 'technical_documentation']);
    Storage::fake('local');
    Mail::fake();
});

/**
 * A dossier opened through the form, with its mandate details and the given documents stored.
 *
 * @param  list<string>  $documents
 */
function dossierForPackChange(SubmissionType $type = SubmissionType::Starter, array $documents = ['turnover_proof', 'technical_documentation']): Submission
{
    $submission = app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Wildthread Ceramics',
        'company_registration_number' => 'NL123456',
        'website_url' => 'https://wildthread.example',
        'first_name' => 'Maya',
        'last_name' => 'Thornton',
        'email' => 'maya@example.com',
        'phone' => '+31 6 12345678',
    ], $type)->submission;

    $submission->contract->update(['filled_fields' => [
        'incorporation_place' => 'Toronto, Canada',
        'founding_year' => '2015',
        'activity' => 'handmade ceramics',
    ]]);

    foreach ($documents as $document) {
        $path = "starter-documents/{$submission->reference}/{$document}.pdf";
        Storage::disk('local')->put($path, 'PDF');
        $submission->uploadedDocuments()->create([
            'type' => $document, 'file_path' => $path, 'original_filename' => "{$document}.pdf",
            'mime_type' => 'application/pdf', 'size_bytes' => 3, 'uploaded_at' => now(),
        ]);
    }

    Mail::fake(); // on isole les envois du changement de pack

    return $submission->fresh();
}

it('switches a Creator dossier to Pro from the journey, carrying over details and documents', function () {
    $old = dossierForPackChange();
    $oldToken = $old->resume_token;
    $documentIds = $old->uploadedDocuments->pluck('id')->sort()->values()->all();

    Livewire::test(StarterJourney::class, ['submission' => $old])
        ->assertSee('Not the right plan?')
        ->assertSee('Switch to the Pro Pack')
        ->set('confirmingPackChange', true)
        ->assertSee('Switch to the Pro Pack?')
        ->assertSee('Your details and documents are carried over to a new file for this plan.')
        ->assertSee('Your current link will stop working')
        ->assertDontSee('sign a new one')                          // mandat pas encore signe
        ->call('changePack')
        ->assertHasNoErrors()
        ->assertRedirect();

    $old->refresh();
    $new = $old->replacedBy;

    expect($new)->not->toBeNull()
        ->and($new->type)->toBe(SubmissionType::Pro)
        ->and($new->only(['company_name', 'company_registration_number', 'website_url', 'first_name', 'last_name', 'email', 'phone', 'locale']))
        ->toBe($old->only(['company_name', 'company_registration_number', 'website_url', 'first_name', 'last_name', 'email', 'phone', 'locale']))
        ->and($new->status)->toBe(SubmissionStatus::InProgress)
        ->and($new->contract->signature_status)->toBe(SignatureStatus::Pending)
        ->and($new->contract->filled_fields)->toMatchArray(['incorporation_place' => 'Toronto, Canada', 'founding_year' => '2015', 'activity' => 'handmade ceramics'])
        // Pieces deplacees (jamais copiees) : memes lignes, memes fichiers, plus rien sur l'ancien dossier.
        ->and($new->uploadedDocuments->pluck('id')->sort()->values()->all())->toBe($documentIds)
        ->and($old->uploadedDocuments()->count())->toBe(0)
        ->and($old->status)->toBe(SubmissionStatus::Cancelled);

    // L'ancien lien est mort ; le nouveau dossier s'ouvre a la signature (pieces completes).
    get(route('get-started.starter.journey', ['dossier' => $oldToken]))
        ->assertNotFound()
        ->assertSee('This link is no longer valid');
    $this->withSession(['starter_status' => 'pack_changed'])
        ->get(route('get-started.starter.journey', ['dossier' => $new->resume_token]))
        ->assertOk()
        ->assertSee('switched to the Pro Pack. Your details and documents have been carried over.')
        ->assertSee('Sign your Responsible Person mandate');

    // Nouveau lien envoye au client, equipe prevenue.
    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $mail) => $mail->hasTo('maya@example.com') && $mail->submission->is($new));
    Mail::assertSent(FunnelNotification::class, fn (FunnelNotification $mail) => $mail->reason === FunnelNotificationReason::PackChanged
        && $mail->submission->is($new));
});

it('asks for a new signature when the mandate was already signed, keeping the signed one on the old dossier', function () {
    $old = dossierForPackChange();
    $old->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => now(), 'signed_file_path' => 'contracts/old.pdf']);
    $old->update(['status' => SubmissionStatus::AwaitingPayment]);

    Livewire::test(StarterJourney::class, ['submission' => $old->fresh()])
        ->set('confirmingPackChange', true)
        ->assertSee('You\'ve already signed your mandate for the Creator Pack: you\'ll sign a new one for the Pro Pack.')
        ->call('changePack')
        ->assertRedirect();

    $old->refresh();
    $new = $old->replacedBy;

    expect($new->contract->signature_status)->toBe(SignatureStatus::Pending)
        ->and($new->status)->toBe(SubmissionStatus::InProgress)
        ->and($old->contract->signature_status)->toBe(SignatureStatus::Signed)   // trace de l'ancien pack
        ->and($old->contract->signed_file_path)->toBe('contracts/old.pdf');

    get(route('get-started.starter.journey', ['dossier' => $new->resume_token]))
        ->assertSee('Sign your Responsible Person mandate');
});

it('opens the new dossier on the documents when the new pack requires one that is missing', function () {
    config()->set('festilaw.pro.required_documents', ['technical_documentation']);
    $old = dossierForPackChange(SubmissionType::Pro, ['technical_documentation']);

    $new = app(ChangeStarterPackAction::class)->execute($old, SubmissionType::Starter);

    expect($new->type)->toBe(SubmissionType::Starter)
        ->and($new->uploadedDocuments)->toHaveCount(1);

    get(route('get-started.starter.journey', ['dossier' => $new->resume_token]))
        ->assertSee('Upload your documents')
        ->assertSee('Proof of turnover');
});

it('refuses to change the pack once a payment is in progress or made, or on a closed dossier', function (Closure $setUp, SubmissionType $target) {
    $old = dossierForPackChange();
    $setUp($old);

    expect(fn () => app(ChangeStarterPackAction::class)->execute($old->fresh(), $target))
        ->toThrow(StarterException::class);

    expect(Submission::count())->toBe(1)
        ->and($old->fresh()->replaced_by_id)->toBeNull();
})->with([
    'checkout in progress' => [fn (Submission $s) => $s->payments()->create([
        'type' => PaymentType::StarterSubscription, 'amount_cents' => 11100, 'currency' => 'EUR',
        'provider' => 'stripe', 'provider_reference' => 'cs_1', 'status' => PaymentStatus::Pending,
    ]), SubmissionType::Pro],
    'already paid' => [fn (Submission $s) => $s->payments()->create([
        'type' => PaymentType::StarterSubscription, 'amount_cents' => 11100, 'currency' => 'EUR',
        'provider' => 'stripe', 'provider_reference' => 'cs_1', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(),
    ]), SubmissionType::Pro],
    'cancelled dossier' => [fn (Submission $s) => $s->update(['status' => SubmissionStatus::Cancelled]), SubmissionType::Pro],
    'same pack' => [fn (Submission $s) => null, SubmissionType::Starter],
    'no online journey' => [fn (Submission $s) => null, SubmissionType::Scale],
]);

it('hides the pack switch while a checkout is in flight', function () {
    $old = dossierForPackChange();
    $old->contract->update(['signature_status' => SignatureStatus::Signed, 'signed_at' => now()]);
    $old->payments()->create([
        'type' => PaymentType::StarterSubscription, 'amount_cents' => 11100, 'currency' => 'EUR',
        'provider' => 'stripe', 'provider_reference' => 'cs_1', 'status' => PaymentStatus::Pending,
    ]);

    Livewire::test(StarterJourney::class, ['submission' => $old->fresh()])
        ->assertDontSee('Not the right plan?')
        ->call('changePack')
        ->assertNoRedirect();

    expect(Submission::count())->toBe(1);
});

it('creates a single replacement on a double click', function () {
    $old = dossierForPackChange();

    $first = app(ChangeStarterPackAction::class)->execute($old, SubmissionType::Pro);
    $second = app(ChangeStarterPackAction::class)->execute($old, SubmissionType::Pro);

    expect($second->id)->toBe($first->id)
        ->and(Submission::count())->toBe(2);
    Mail::assertSent(FunnelNotification::class, 1);
});

it('purges a replaced dossier after the retention delay, never the documents of its replacement', function () {
    $old = dossierForPackChange();
    $new = app(ChangeStarterPackAction::class)->execute($old, SubmissionType::Pro);

    // Un autre dossier annule (non remplace) au lien expire depuis longtemps : conserve (hors perimetre).
    $otherCancelled = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Cancelled,
        'resume_expires_at' => now()->subDays(200),
    ]);

    $this->travel(91)->days();
    artisan('festilaw:purge-abandoned-dossiers')->assertSuccessful();

    expect(Submission::find($old->id))->toBeNull()
        ->and(Submission::find($otherCancelled->id))->not->toBeNull()
        ->and($new->fresh())->not->toBeNull()
        ->and($new->fresh()->uploadedDocuments)->toHaveCount(2);

    foreach ($new->fresh()->uploadedDocuments as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }
});
