<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Mail\AdminMessageToClient;
use App\Models\Submission;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie au client, depuis le back-office, un email libre (objet + message). Contrairement aux envois
 * peripheriques du parcours, l'echec n'est PAS avale : l'operateur doit savoir si son message est parti.
 * Un envoi reussi met a jour le dossier (updated_at) : pour une demande de contact, c'est la date du dernier
 * echange qui fait courir le delai de suppression (RGPD).
 */
final readonly class SendAdminMessageAction
{
    public function execute(Submission $submission, string $subject, string $body): void
    {
        Mail::to($submission->email)
            ->locale($submission->locale ?: config('app.locale'))
            ->send(new AdminMessageToClient($submission, $subject, $body));

        $submission->touch();
    }
}
