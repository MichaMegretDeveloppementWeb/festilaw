<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Submission\SubmissionStatus;
use App\Mail\StarterResponsiblePersonIssued;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Finalise un dossier STARTER : enregistre l'adresse de Personne Responsable UE delivree, passe le
 * dossier a "termine", et previent le client par email (envoi non bloquant, dans sa langue).
 */
final readonly class IssueResponsiblePersonAction
{
    public function execute(Submission $submission, string $address): void
    {
        $submission->update([
            'eu_rp_address' => $address,
            'status' => SubmissionStatus::Completed,
        ]);

        if ((string) $submission->email === '') {
            return;
        }

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new StarterResponsiblePersonIssued($submission));
        } catch (Throwable $e) {
            Log::error('Failed to send the Responsible Person issued email.', [
                'exception' => $e,
                'submission' => $submission->id,
            ]);
        }
    }
}
