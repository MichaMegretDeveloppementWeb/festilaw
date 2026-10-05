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

        // Tous les mandats du dossier (en vigueur, en attente, remplaces par un changement de pack) : le mandat
        // signe et, s'il existe, celui contresigne par Festilaw (Q3), fichiers prives a effacer aussi (RGPD).
        foreach ($submission->contracts as $contract) {
            foreach ([$contract->signed_file_path, $contract->countersigned_file_path] as $path) {
                if ($path !== null && $path !== '') {
                    $disk->delete($path);
                }
            }
        }
    }
}
