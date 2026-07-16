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

        Log::channel('payments')->notice('admin.submission.status_changed', [
            'submission' => $submission->id,
            'reference' => $submission->reference,
            'from' => $from->value,
            'to' => $status->value,
        ]);
    }
}
