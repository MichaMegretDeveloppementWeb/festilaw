<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\UploadedDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one uploaded document of a dossier. Access is the dossier's resume token (capability URL);
 * the document must belong to that dossier, so a foreign id cannot be read through it.
 */
final class StarterDocumentDownloadController extends Controller
{
    public function __invoke(string $locale, Submission $dossier, UploadedDocument $document): StreamedResponse
    {
        abort_unless($document->submission_id === $dossier->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }
}
