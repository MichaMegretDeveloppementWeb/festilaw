<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Mail\CountersignedContractAvailable;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Enregistre le contrat contresigne par Festilaw (depose par l'admin) sur le dossier : fichier sur le
 * disque prive, reference dans le contrat, et notification optionnelle du client par email (avec le PDF
 * en piece jointe, envoi non bloquant, dans la langue du client).
 */
final readonly class UploadCountersignedContractAction
{
    /** @param  string  $storedPath  Chemin du fichier deja stocke sur le disque prive ('local'). */
    public function execute(Submission $submission, string $storedPath, bool $notifyClient): void
    {
        $contract = $submission->contract;

        if ($contract === null) {
            return;
        }

        // Remplacement : on efface l'ancien fichier contresigne s'il differe.
        $previous = (string) $contract->countersigned_file_path;
        if ($previous !== '' && $previous !== $storedPath && Storage::disk('local')->exists($previous)) {
            Storage::disk('local')->delete($previous);
        }

        $contract->update([
            'countersigned_file_path' => $storedPath,
            'countersigned_at' => now(),
        ]);

        if ($notifyClient) {
            $this->emailClient($submission);
        }
    }

    private function emailClient(Submission $submission): void
    {
        if ((string) $submission->email === '') {
            return;
        }

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new CountersignedContractAvailable($submission));
        } catch (Throwable $e) {
            Log::error('Failed to send the counter-signed contract email.', [
                'exception' => $e,
                'submission' => $submission->id,
            ]);
        }
    }
}
