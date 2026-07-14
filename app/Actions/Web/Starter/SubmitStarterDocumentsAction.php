<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Document\DocumentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores ALL required STARTER documents at once (single "continue" action), then advances the
 * submission to "awaiting payment". Atomic: files are stored first (I/O), then the rows + status
 * are written in a transaction. Any failure (storage or DB) removes the freshly-stored files so no
 * orphan is left. Every I/O error is converted to a typed StarterException.
 */
final readonly class SubmitStarterDocumentsAction
{
    /** @param  array<string, UploadedFile>  $files  keyed by DocumentType value */
    public function execute(Submission $submission, array $files): void
    {
        $requiredValues = array_map(
            static fn (string $value): string => DocumentType::from($value)->value,
            (array) config('festilaw.starter.required_documents', []),
        );

        $missing = array_values(array_diff($requiredValues, array_keys(array_filter($files))));
        if ($missing !== []) {
            throw StarterException::documentsMissing($submission->id, $missing);
        }

        // On ne conserve que les documents requis (ignore tout extra).
        $files = array_intersect_key($files, array_flip($requiredValues));

        // 1. Stockage sur disque prive (I/O, hors transaction), avec rollback des fichiers si echec.
        $stored = [];
        try {
            foreach ($files as $typeValue => $file) {
                $stored[$typeValue] = $this->storeOnPrivateDisk($submission, DocumentType::from($typeValue), $file);
            }
        } catch (Throwable $e) {
            $this->deleteStored($stored);
            throw $e instanceof StarterException
                ? $e
                : StarterException::documentStorageFailed($submission->id, 'batch', $e);
        }

        // 2. Enregistrement + avancement (transaction). Si elle echoue, on nettoie les fichiers stockes.
        try {
            DB::transaction(function () use ($submission, $files, $stored): void {
                foreach ($stored as $typeValue => $meta) {
                    $submission->uploadedDocuments()->create([
                        'type' => DocumentType::from($typeValue),
                        'file_path' => $meta['path'],
                        'original_filename' => $files[$typeValue]->getClientOriginalName(),
                        'mime_type' => $meta['mime'],
                        'size_bytes' => $meta['size'],
                        'uploaded_at' => now(),
                    ]);
                }

                $submission->update(['status' => SubmissionStatus::AwaitingPayment]);
            });
        } catch (Throwable $e) {
            $this->deleteStored($stored);
            throw $e;
        }
    }

    /**
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

    /** @param  array<string, array{path: string, size: int, mime: string}>  $stored */
    private function deleteStored(array $stored): void
    {
        foreach ($stored as $meta) {
            Storage::disk('local')->delete($meta['path']);
        }
    }
}
