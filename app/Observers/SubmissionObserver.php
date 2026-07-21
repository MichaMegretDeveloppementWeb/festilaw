<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Submission;
use Illuminate\Support\Facades\Storage;

/**
 * Efface les fichiers physiques d'un dossier quand il est supprime (RGPD : droit a l'effacement +
 * minimisation). Les lignes enfant sont supprimees par cascade DB, mais les fichiers sur le disque
 * prive (justificatifs televerses, mandat signe) doivent l'etre explicitement.
 */
final class SubmissionObserver
{
    public function deleting(Submission $submission): void
    {
        $disk = Storage::disk('local');

        foreach ($submission->uploadedDocuments as $document) {
            if ($document->file_path !== null && $document->file_path !== '') {
                $disk->delete($document->file_path);
            }
        }

        $signedPath = $submission->contract?->signed_file_path;
        if ($signedPath !== null && $signedPath !== '') {
            $disk->delete($signedPath);
        }

        // Le mandat contresigne par Festilaw (Q3) est un fichier prive de plus : a effacer aussi (RGPD).
        $countersignedPath = $submission->contract?->countersigned_file_path;
        if ($countersignedPath !== null && $countersignedPath !== '') {
            $disk->delete($countersignedPath);
        }
    }
}
