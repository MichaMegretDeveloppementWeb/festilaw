<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use App\Models\UploadedDocument;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\DB;

/**
 * Records an uploaded STARTER document and, if the dossier becomes complete (contract signed +
 * all required documents), advances the submission to "awaiting payment".
 *
 * @phpstan-type DocumentData array{type: string, file_path: string, original_filename: string, mime_type: string, size_bytes: int}
 */
final readonly class StoreStarterDocumentAction
{
    public function __construct(private StarterDossierResolver $resolver) {}

    /** @param  DocumentData  $data */
    public function execute(Submission $submission, array $data): UploadedDocument
    {
        return DB::transaction(function () use ($submission, $data): UploadedDocument {
            $document = $submission->uploadedDocuments()->create([
                'type' => $data['type'],
                'file_path' => $data['file_path'],
                'original_filename' => $data['original_filename'],
                'mime_type' => $data['mime_type'],
                'size_bytes' => $data['size_bytes'],
                'uploaded_at' => now(),
            ]);

            $submission->loadMissing(['contract', 'uploadedDocuments']);

            if ($this->resolver->resolve($submission)->isComplete) {
                $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
            }

            return $document;
        });
    }
}
