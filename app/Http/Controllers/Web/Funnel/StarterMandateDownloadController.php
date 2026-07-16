<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the signed Responsible Person mandate of a dossier. Access is the dossier's resume token
 * (capability URL, resolved + scoped by the {dossier} binding) · the file lives on the private disk.
 */
final class StarterMandateDownloadController extends Controller
{
    public function __invoke(Submission $dossier): StreamedResponse
    {
        $path = (string) ($dossier->contract?->signed_file_path ?? '');
        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'festilaw-mandate-'.$dossier->reference.'.pdf');
    }
}
