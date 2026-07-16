<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\UploadedDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement admin (authentifie) d'une piece televersee. Le fichier est sur le disque prive ;
 * on verifie qu'il appartient bien au dossier de l'URL.
 */
final class AdminDocumentDownloadController extends Controller
{
    public function __invoke(Submission $submission, UploadedDocument $document): StreamedResponse
    {
        abort_unless($document->submission_id === $submission->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }
}
