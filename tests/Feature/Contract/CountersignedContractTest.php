<?php

use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Livewire\Admin\SubmissionDetail;
use App\Mail\CountersignedContractAvailable;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** A paid dossier with a signed mandate, reachable by the token 'cs-dossier'. */
function dossierWithContract(): Submission
{
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid,
        'resume_token' => 'cs-dossier',
        'resume_expires_at' => null,
        'email' => 'client@example.com',
        'locale' => 'en',
    ]);
    $submission->contract()->create([
        'signature_status' => SignatureStatus::Signed,
        'signed_at' => now(),
        'signed_file_path' => 'contracts/mandate.pdf',
        'filled_fields' => [],
    ]);

    return $submission->fresh();
}

it('lets an admin upload the counter-signed contract and notify the client', function () {
    Storage::fake('local');
    Mail::fake();
    $dossier = dossierWithContract();
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier])
        ->set('countersigned', UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf'))
        ->set('notifyClientOnCountersign', true)
        ->call('uploadCountersigned')
        ->assertHasNoErrors();

    $path = 'contracts/countersigned/'.$dossier->id.'.pdf';
    Storage::disk('local')->assertExists($path);

    expect($dossier->contract->fresh()->countersigned_file_path)->toBe($path)
        ->and($dossier->contract->fresh()->countersigned_at)->not->toBeNull();

    Mail::assertSent(CountersignedContractAvailable::class, fn ($m) => $m->hasTo('client@example.com'));
});

it('can upload without notifying the client', function () {
    Storage::fake('local');
    Mail::fake();
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => dossierWithContract()])
        ->set('countersigned', UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf'))
        ->set('notifyClientOnCountersign', false)
        ->call('uploadCountersigned')
        ->assertHasNoErrors();

    Mail::assertNothingSent();
});

it('rejects a non-pdf counter-signed upload', function () {
    Storage::fake('local');
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => dossierWithContract()])
        ->set('countersigned', UploadedFile::fake()->create('contract.txt', 10, 'text/plain'))
        ->call('uploadCountersigned')
        ->assertHasErrors('countersigned');
});

it('refuses a counter-signed upload when the client has not signed the mandate yet', function () {
    Storage::fake('local');
    Mail::fake();
    actingAs(User::factory()->create());

    // Dossier dont le mandat est encore EN ATTENTE (client n'a pas signe).
    $dossier = Submission::factory()->starter()->create(['email' => 'client@example.com']);
    $dossier->contract()->create(['signature_status' => SignatureStatus::Pending, 'filled_fields' => []]);

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->set('countersigned', UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf'))
        ->set('notifyClientOnCountersign', true)
        ->call('uploadCountersigned')
        ->assertHasErrors('countersigned');

    // Rien n'a ete stocke ni notifie.
    Storage::disk('local')->assertMissing('contracts/countersigned/'.$dossier->id.'.pdf');
    expect($dossier->contract->fresh()->countersigned_file_path)->toBeNull();
    Mail::assertNothingSent();
});

it('shows the counter-signed contract to the client and streams it', function () {
    Storage::fake('local');
    $dossier = dossierWithContract();
    $path = 'contracts/countersigned/'.$dossier->id.'.pdf';
    $dossier->contract->update(['countersigned_file_path' => $path, 'countersigned_at' => now()]);
    Storage::disk('local')->put($path, '%PDF-1.4 fake');

    get(route('my-project', ['dossier' => 'cs-dossier']))
        ->assertOk()
        ->assertSee('Countersigned contract');

    get(route('get-started.starter.countersigned', ['dossier' => 'cs-dossier']))->assertOk();
});

it('returns 404 when no counter-signed contract exists yet', function () {
    dossierWithContract();

    get(route('get-started.starter.countersigned', ['dossier' => 'cs-dossier']))->assertNotFound();
});
