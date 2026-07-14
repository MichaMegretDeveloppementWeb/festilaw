<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Document\DocumentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use App\Models\UploadedDocument;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores an uploaded STARTER document on the private disk and records it. If the dossier becomes
 * complete (contract signed + all required documents), advances the submission to "awaiting payment".
 * All filesystem I/O is converted to a typed StarterException so the caller never faces a raw error.
 */
final readonly class StoreStarterDocumentAction
{
    public function __construct(private StarterDossierResolver $resolver) {}

    public function execute(Submission $submission, DocumentType $type, UploadedFile $file): UploadedDocument
    {
        // Stockage (I/O) hors transaction : toute erreur devient une exception typee.
        $stored = $this->storeOnPrivateDisk($submission, $type, $file);

        return DB::transaction(function () use ($submission, $type, $file, $stored): UploadedDocument {
            $document = $submission->uploadedDocuments()->create([
                'type' => $type,
                'file_path' => $stored['path'],
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $stored['mime'],
                'size_bytes' => $stored['size'],
                'uploaded_at' => now(),
            ]);

            // load() (pas loadMissing) : rafraichit meme si les relations sont deja chargees.
            $submission->load(['contract', 'uploadedDocuments']);

            if ($this->resolver->resolve($submission)->isComplete) {
                $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
            }

            return $document;
        });
    }

    /**
     * Copies the uploaded file to the private disk and reads its metadata FROM the stored file
     * (never from the Livewire temp file, whose size/mime lookup is unreliable once copied).
     *
     * @return array{path: string, size: int, mime: string}
     */
    private function storeOnPrivateDisk(Submission $submission, DocumentType $type, UploadedFile $file): array
    {
        try {
            $disk = Storage::disk('local');
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $path = $file->storeAs(
                "starter-documents/{$submission->reference}",
                Str::uuid()->toString().'.'.$extension,
                'local',
            );

            if (! is_string($path) || $path === '') {
                throw StarterException::documentStorageFailed($submission->id, $type->value);
            }

            return [
                'path' => $path,
                'size' => $disk->size($path),
                'mime' => $disk->mimeType($path) ?: ($file->getClientMimeType() ?: 'application/octet-stream'),
            ];
        } catch (StarterException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw StarterException::documentStorageFailed($submission->id, $type->value, $e);
        }
    }
}
