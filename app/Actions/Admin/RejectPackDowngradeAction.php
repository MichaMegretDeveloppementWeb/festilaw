<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Submission\PackChangeStatus;
use App\Exceptions\Admin\AdminActionException;
use App\Mail\PackDowngradeRejected;
use App\Models\PackChange;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Festilaw refuse la demande de retour au Creator (SC12), avec un message optionnel transmis au client. Le
 * Pro continue comme avant ; le client peut refaire une demande plus tard.
 */
final readonly class RejectPackDowngradeAction
{
    public function execute(PackChange $downgrade, ?int $adminId, ?string $note): void
    {
        if ($downgrade->isUpgrade() || $downgrade->status !== PackChangeStatus::Requested) {
            throw AdminActionException::packDowngradeNotPending($downgrade->id);
        }

        $note = trim((string) $note) !== '' ? trim((string) $note) : null;
        $downgrade->update([
            'status' => PackChangeStatus::Rejected,
            'note' => $note,
            'decided_by' => $adminId,
            'decided_at' => now(),
        ]);

        $submission = $downgrade->submission;

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new PackDowngradeRejected($submission, $note));
        } catch (Throwable $e) {
            Log::error('Failed to send the pack downgrade rejection email.', ['exception' => $e, 'submission' => $submission->id]);
        }
    }
}
