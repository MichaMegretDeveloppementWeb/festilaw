<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Web\Starter\ApplyPackDowngradeAction;
use App\Enums\Submission\PackChangeStatus;
use App\Exceptions\Admin\AdminActionException;
use App\Mail\PackDowngradeApproved;
use App\Models\PackChange;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Festilaw valide la demande de retour au Creator (SC12), apres avoir verifie l'eligibilite (justificatif de
 * CA, 9 produits maximum). L'annee d'effet est celle qui suit la derniere annee payee : le Pro reste valable
 * jusque-la. Si ce renouvellement est deja du, le retour s'applique tout de suite. Le client est prevenu.
 */
final readonly class ApprovePackDowngradeAction
{
    public function __construct(
        private PackChangeService $packChanges,
        private ApplyPackDowngradeAction $applyDowngrade,
    ) {}

    public function execute(PackChange $downgrade, ?int $adminId): void
    {
        if ($downgrade->isUpgrade() || $downgrade->status !== PackChangeStatus::Requested) {
            throw AdminActionException::packDowngradeNotPending($downgrade->id);
        }

        $submission = $downgrade->submission;
        $downgrade->update([
            'status' => PackChangeStatus::Approved,
            'effective_year' => $this->packChanges->downgradeEffectiveYear($submission),
            'decided_by' => $adminId,
            'decided_at' => now(),
        ]);

        if ($downgrade->effective_year <= (int) now()->year) {
            $this->applyDowngrade->execute($downgrade);
        }

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new PackDowngradeApproved($submission->fresh(), $downgrade->fresh()));
        } catch (Throwable $e) {
            Log::error('Failed to send the pack downgrade approval email.', ['exception' => $e, 'submission' => $submission->id]);
        }
    }
}
