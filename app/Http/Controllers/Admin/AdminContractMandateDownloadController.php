<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement admin (authentifie) du mandat signe d'UN mandat precis du dossier, depuis le disque prive :
 * les mandats remplaces par un changement de pack apres paiement (SC12) restent consultables. Le mandat doit
 * appartenir au dossier (binding scope).
 */
final class AdminContractMandateDownloadController extends Controller
{
    public function __invoke(Submission $submission, Contract $contract): StreamedResponse
    {
        $path = (string) ($contract->signed_file_path ?? '');

        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        $pack = strtolower(str_replace(' Pack', '', $contract->packType()->label()));

        return Storage::disk('local')->download($path, 'festilaw-mandate-'.$submission->reference.'-'.$pack.'.pdf');
    }
}
