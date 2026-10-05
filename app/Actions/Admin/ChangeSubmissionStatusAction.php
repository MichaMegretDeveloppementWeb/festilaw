<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;

/**
 * Changement manuel du statut d'un dossier depuis le back-office. Le traitement des dossiers est
 * manuel (pas de machine a etats stricte cote admin) : l'operateur choisit le statut cible. Trace
 * dans le canal payments (audit) car un changement de statut peut avoir des consequences metier.
 *
 * "Annule" marque la fin de la relation : l'acces client (lien magique) est ferme, comme l'annonce la
 * politique de confidentialite. Rouvrir le dossier retablit un lien valable (decision de Festilaw, 05/10/2026).
 */
final readonly class ChangeSubmissionStatusAction
{
    public function execute(Submission $submission, SubmissionStatus $status): void
    {
        $from = $submission->status;

        if ($from === $status) {
            return;
        }

        $submission->update(['status' => $status]);

        $access = null;
        if ($status === SubmissionStatus::Cancelled) {
            $submission->closeAccess();
            $access = 'closed';
        } elseif ($from === SubmissionStatus::Cancelled) {
            $submission->refreshAccess();
            $access = 'reopened';
        }

        Log::channel('payments')->notice('admin.submission.status_changed', array_filter([
            'submission' => $submission->id,
            'reference' => $submission->reference,
            'from' => $from->value,
            'to' => $status->value,
            'access' => $access,
        ]));
    }
}
