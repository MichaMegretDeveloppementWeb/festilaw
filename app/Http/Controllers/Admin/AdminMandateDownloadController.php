<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement admin (authentifie) du mandat signe d'un dossier, depuis le disque prive.
 */
final class AdminMandateDownloadController extends Controller
{
    public function __invoke(Submission $submission): StreamedResponse
    {
        $path = (string) ($submission->contract?->signed_file_path ?? '');

        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'festilaw-mandate-'.$submission->reference.'.pdf');
    }
}
