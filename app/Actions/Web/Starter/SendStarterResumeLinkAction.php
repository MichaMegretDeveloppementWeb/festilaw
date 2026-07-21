<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Mail\StarterResumeLink;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the visitor their STARTER resume link. Peripheral side effect: a failure is logged but never
 * breaks the flow (cf. gestion-erreurs, erreurs partielles non bloquantes).
 */
final readonly class SendStarterResumeLinkAction
{
    public function execute(Submission $submission): void
    {
        if ((string) $submission->email === '') {
            return;
        }

        // Rotation systematique a chaque demande de lien : le mail porte un token frais et l'eventuel
        // lien precedent devient caduc (plus jamais de lien perime/fuite reutilisable).
        $submission->regenerateResumeToken();

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new StarterResumeLink($submission));
        } catch (Throwable $e) {
            Log::error('Failed to send the STARTER resume link.', [
                'exception' => $e,
                'submission' => $submission->id,
            ]);
        }
    }
}
