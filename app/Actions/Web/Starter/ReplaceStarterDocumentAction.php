<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Document\DocumentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Replaces a SINGLE already-uploaded STARTER document (the visitor spotted a wrong file while reviewing a
 * completed step). Stores the new file first (I/O, out of the transaction), swaps the row inside a
 * transaction, then removes the old file. On any failure the freshly-stored file is deleted so no orphan
 * is left. Never touches the dossier status : it is a pure correction, the parcours stays where it is.
 */
final readonly class ReplaceStarterDocumentAction
{
    public function execute(Submission $submission, DocumentType $type, UploadedFile $file): void
    {
        $existing = $submission->uploadedDocuments()->where('type', $type)->first();
        if ($existing === null) {
            throw StarterException::documentNotFound($submission->id, $type->value);
        }

        $oldPath = (string) $existing->file_path;
        $stored = $this->storeOnPrivateDisk($submission, $type, $file);

        try {
            DB::transaction(function () use ($existing, $stored, $file): void {
                $existing->update([
                    'file_path' => $stored['path'],
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $stored['mime'],
                    'size_bytes' => $stored['size'],
                    'uploaded_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($stored['path']);
            throw $e;
        }

        // Remplacement reussi : on supprime l'ancien fichier (best-effort, ne bloque jamais).
        if ($oldPath !== '' && $oldPath !== $stored['path']) {
            Storage::disk('local')->delete($oldPath);
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
}
