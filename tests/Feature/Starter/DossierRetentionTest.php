<?php

use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('deletes uploaded files from the private disk when a dossier is deleted (GDPR erasure)', function () {
    Storage::fake('local');

    $submission = Submission::factory()->starter()->create();
    UploadedDocument::factory()->for($submission)->create(['file_path' => 'starter-documents/x/doc.pdf']);
    Storage::disk('local')->put('starter-documents/x/doc.pdf', 'data');

    Storage::disk('local')->assertExists('starter-documents/x/doc.pdf');

    $submission->delete();

    Storage::disk('local')->assertMissing('starter-documents/x/doc.pdf');
});

it('purges abandoned expired dossiers but keeps paid and still-fresh ones', function () {
    $abandoned = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::InProgress,
        'resume_expires_at' => now()->subDays(200),
    ]);
    $paid = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid,
        'resume_expires_at' => now()->subDays(200),
    ]);
    $fresh = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::InProgress,
        'resume_expires_at' => now()->subDays(10),
    ]);

    artisan('festilaw:purge-abandoned-dossiers')->assertSuccessful();

    expect(Submission::find($abandoned->id))->toBeNull()
        ->and(Submission::find($paid->id))->not->toBeNull()
        ->and(Submission::find($fresh->id))->not->toBeNull();
});
