<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;

/**
 * Le client retire sa demande de retour au Creator (SC12), tant qu'elle n'est pas appliquee : en attente de
 * Festilaw ou deja validee pour le prochain renouvellement. Son Pro continue comme avant.
 */
final readonly class WithdrawPackDowngradeAction
{
    public function execute(Submission $submission): void
    {
        $submission->packChanges()->reorder()
            ->whereIn('status', [PackChangeStatus::Requested, PackChangeStatus::Approved])
            ->where('to_pack', SubmissionType::Starter)
            ->update(['status' => PackChangeStatus::Withdrawn]);
    }
}
