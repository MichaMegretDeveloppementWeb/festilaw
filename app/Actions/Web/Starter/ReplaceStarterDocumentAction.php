<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Document\DocumentType;
use App\Exceptions\Starter\StarterException;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Replaces a SINGLE already-uploaded STARTER document (the visitor spotted a wrong file while reviewing a
 * completed step). Stores the new file first (I/O, out of the transaction), swaps the row inside a
 * transaction, then removes the old file. On any failure the freshly-stored file is deleted so no orphan
 * is left. Never touches the dossier status : it is a pure correction, the parcours stays where it is.
 *
 * With $createIfMissing, a document of that type not uploaded yet is added instead (e.g. the recent proof of
 * turnover of a Pro client asking to switch back to Creator, SC12).
 */
final readonly class ReplaceStarterDocumentAction
{
    public function execute(Submission $submission, DocumentType $type, UploadedFile $file, bool $createIfMissing = false): void
    {
        $existing = $submission->uploadedDocuments()->where('type', $type)->first();
        if ($existing === null && ! $createIfMissing) {
            throw StarterException::documentNotFound($submission->id, $type->value);
        }

        $oldPath = (string) ($existing?->file_path ?? '');
        $stored = $this->storeOnPrivateDisk($submission, $type, $file);

        try {
            DB::transaction(function () use ($submission, $type, $existing, $stored, $file): void {
                $attributes = [
                    'file_path' => $stored['path'],
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $stored['mime'],
                    'size_bytes' => $stored['size'],
                    'uploaded_at' => now(),
                ];

                $existing !== null
                    ? $existing->update($attributes)
                    : $submission->uploadedDocuments()->create(['type' => $type, ...$attributes]);
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($stored['path']);
            throw $e;
        }

        // Remplacement reussi : on supprime l'ancien fichier (au mieux : ne bloque jamais, mais un echec est
        // journalise, l'ancien fichier restant une donnee personnelle a effacer).
        if ($oldPath !== '' && $oldPath !== $stored['path']) {
            $this->deleteQuietly($oldPath);
        }
    }

    /**
     * @return array{path: string, size: int, mime: string}
     */
    private function storeOnPrivateDisk(Submission $submission, DocumentType $type, UploadedFile $file): array
    {
        $path = null;

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
            // Fichier ecrit mais illisible ensuite (taille / type) : on ne le laisse pas orphelin.
            if (is_string($path) && $path !== '') {
                $this->deleteQuietly($path);
            }

            throw StarterException::documentStorageFailed($submission->id, $type->value, $e);
        }
    }

    /**
     * Nettoyage au mieux d'un fichier prive : un echec ne doit jamais masquer l'erreur d'origine ni casser le
     * parcours, mais le fichier laisse en place (donnees personnelles) est journalise pour etre retrouve.
     */
    private function deleteQuietly(string $path): void
    {
        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable $e) {
            Log::warning('Private file left behind: delete failed.', ['path' => $path, 'exception' => $e]);
        }
    }
}
