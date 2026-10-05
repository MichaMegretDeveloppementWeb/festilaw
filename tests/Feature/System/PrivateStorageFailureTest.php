<?php

declare(strict_types=1);

use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Actions\Web\Starter\ReplaceStarterDocumentAction;
use App\Actions\Web\Starter\SubmitStarterDocumentsAction;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Starter\StarterException;
use App\Livewire\Admin\SubmissionDetail;
use App\Mail\CountersignedContractAvailable;
use App\Models\Contract;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 | Fichiers prives (pieces des clients, mandats, contrats contresignes) : une ecriture ou une suppression ratee
 | ne passe plus en silence. Pas de chemin enregistre sans fichier, pas de fichier RGPD laisse sans trace.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

/**
 * Disque prive de test dont le stockage sous-jacent echoue comme un vrai disque plein ou un fichier verrouille
 * (l'adaptateur Flysystem leve, comme le fait le disque local). C'est le reglage `throw` du disque prive qui
 * decide alors si l'echec remonte ou devient un `false` silencieux. Renvoie un disque normal sur le meme
 * stockage, pour le retablir.
 *
 * @param  list<string>  $failingDeletes
 */
function failingPrivateDisk(bool $failWrites = false, array $failingDeletes = []): FilesystemAdapter
{
    $healthy = Storage::fake('local');
    $root = $healthy->path('');
    $config = $healthy->getConfig();

    $adapter = new class($root, $failWrites, $failingDeletes) extends LocalFilesystemAdapter
    {
        /** @param  list<string>  $failingDeletes */
        public function __construct(string $root, private bool $failWrites, private array $failingDeletes)
        {
            parent::__construct($root);
        }

        public function write(string $path, string $contents, Config $config): void
        {
            if ($this->failWrites) {
                throw UnableToWriteFile::atLocation($path, 'disk full (test)');
            }

            parent::write($path, $contents, $config);
        }

        public function writeStream(string $path, $contents, Config $config): void
        {
            if ($this->failWrites) {
                throw UnableToWriteFile::atLocation($path, 'disk full (test)');
            }

            parent::writeStream($path, $contents, $config);
        }

        public function delete(string $path): void
        {
            if (in_array($path, $this->failingDeletes, true)) {
                throw UnableToDeleteFile::atLocation($path, 'file locked (test)');
            }

            parent::delete($path);
        }
    };

    Storage::set('local', new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config));

    return $healthy;
}

it('makes the private disk raise its failures instead of returning false', function () {
    expect(config('filesystems.disks.local.throw'))->toBeTrue();
});

it('does not mark a contract as countersigned, nor tell the client, when the PDF cannot be written', function () {
    failingPrivateDisk(failWrites: true);
    $dossier = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid, 'email' => 'client@example.com']);
    $dossier->contract()->create(['signature_status' => SignatureStatus::Signed, 'signed_at' => now(), 'filled_fields' => []]);
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->set('countersigned', UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf'))
        ->set('notifyClientOnCountersign', true)
        ->call('uploadCountersigned')
        ->assertDispatched('admin-toast', type: 'error');

    expect($dossier->contract->fresh()->countersigned_file_path)->toBeNull()
        ->and($dossier->contract->fresh()->countersigned_at)->toBeNull();
    Mail::assertNotSent(CountersignedContractAvailable::class);
});

it('keeps a signed mandate without a path when its PDF cannot be written, so the minute catch-up retries it', function () {
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', ['api_key' => 'testkey', 'api_application_id' => null, 'api_base_url' => 'https://www.signwell.com/api/v1', 'test_mode' => true]);
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF-BYTES', 200, ['Content-Type' => 'application/pdf'])]);
    $contract = Contract::factory()->for(Submission::factory()->starter())->create([
        'signature_status' => SignatureStatus::Signed,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'DOC1',
        'signed_file_path' => null,
        'signed_at' => now(),
    ]);

    $disk = failingPrivateDisk(failWrites: true);
    app(MarkContractSignedAction::class)->backfillSignedDocument($contract);
    expect($contract->fresh()->signed_file_path)->toBeNull();

    // Disque retabli : le rattrapage enregistre le mandat.
    Storage::set('local', $disk);
    $this->artisan('festilaw:backfill-signed-pdfs')->assertOk();
    expect($contract->fresh()->signed_file_path)->toBe('contracts/DOC1.pdf');
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
});

it('refuses the documents with a clear error, saving nothing, when they cannot be written', function () {
    failingPrivateDisk(failWrites: true);
    $submission = Submission::factory()->starter()->create();
    Contract::factory()->for($submission)->signed()->create();

    expect(fn () => app(SubmitStarterDocumentsAction::class)->execute($submission, [
        'turnover_proof' => UploadedFile::fake()->create('turnover.pdf', 100, 'application/pdf'),
        'technical_documentation' => UploadedFile::fake()->create('tech.pdf', 100, 'application/pdf'),
    ]))->toThrow(StarterException::class);

    expect($submission->uploadedDocuments()->count())->toBe(0);
});

it('replaces a document even when the old file cannot be deleted, and logs the file left behind', function () {
    failingPrivateDisk(failingDeletes: ['starter-documents/old-turnover.pdf']);
    Storage::disk('local')->put('starter-documents/old-turnover.pdf', 'OLD');
    $submission = Submission::factory()->starter()->create();
    $document = $submission->uploadedDocuments()->create([
        'type' => DocumentType::TurnoverProof,
        'file_path' => 'starter-documents/old-turnover.pdf',
        'original_filename' => 'old.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3,
        'uploaded_at' => now(),
    ]);
    Log::spy();

    app(ReplaceStarterDocumentAction::class)->execute($submission, DocumentType::TurnoverProof, UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'));

    expect($document->fresh()->original_filename)->toBe('new.pdf');
    Storage::disk('local')->assertExists($document->fresh()->file_path);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'left behind')
        && $context['path'] === 'starter-documents/old-turnover.pdf');
});

it('keeps purging the other abandoned files when one cannot be erased, and logs the failure', function () {
    failingPrivateDisk(failingDeletes: ['starter-documents/locked.pdf']);
    $abandoned = fn (string $file): Submission => tap(Submission::factory()->starter()->create([
        'status' => SubmissionStatus::InProgress,
        'resume_expires_at' => now()->subDays(100),
    ]), function (Submission $dossier) use ($file): void {
        Storage::disk('local')->put($file, 'PII');
        $dossier->uploadedDocuments()->create([
            'type' => DocumentType::TurnoverProof,
            'file_path' => $file,
            'original_filename' => 'turnover.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 3,
            'uploaded_at' => now(),
        ]);
    });
    $locked = $abandoned('starter-documents/locked.pdf');
    $purgeable = $abandoned('starter-documents/free.pdf');
    Log::spy();

    $this->artisan('festilaw:purge-abandoned-dossiers')
        ->expectsOutputToContain('Purged 1 abandoned dossier(s). 1 failed')
        ->assertOk();

    expect(Submission::find($purgeable->id))->toBeNull()
        ->and(Submission::find($locked->id))->not->toBeNull()
        ->and($locked->uploadedDocuments()->count())->toBe(1);
    Storage::disk('local')->assertMissing('starter-documents/free.pdf');
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'purge failed')
        && $context['submission'] === $locked->id);
});
