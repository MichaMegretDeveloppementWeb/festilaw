<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\PackChange;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Applique le passage au Pro une fois sa difference payee (SC12), quel que soit le chemin de confirmation
 * (webhook, retour, reconciliation, verification depuis le back-office) : appelee par
 * MarkPaymentSucceededAction, DANS sa transaction, une seule fois par paiement. Le dossier devient Pro, le
 * mandat Pro signe entre en vigueur et l'ancien mandat Creator est conserve (remplace). Les e-mails partent
 * apres commit (MarkPaymentSucceededAction).
 *
 * Une montee abandonnee par le client mais payee quand meme (checkout reste ouvert) est appliquee : l'argent
 * recu fait foi. Jamais sur un dossier annule, ni sans mandat Pro signe (journalise pour traitement manuel).
 */
final readonly class ApplyPackUpgradeAction
{
    /** @return bool Whether the upgrade was applied by this call. */
    public function execute(Payment $payment): bool
    {
        $submission = $payment->submission()->first();

        $upgrade = PackChange::query()
            ->where('payment_id', $payment->getKey())
            ->whereIn('status', [PackChangeStatus::Open, PackChangeStatus::Cancelled])
            ->with('contract')
            ->first();

        if ($submission === null || $upgrade === null || $submission->status === SubmissionStatus::Cancelled
            || $upgrade->contract?->signature_status !== SignatureStatus::Signed) {
            Log::channel('payments')->error('pack_upgrade.not_applied', [
                'payment' => $payment->getKey(),
                'submission' => $payment->submission_id,
                'pack_change' => $upgrade?->getKey(),
            ]);

            return false;
        }

        $submission->contracts()->reorder()
            ->where('role', ContractRole::Current)
            ->update(['role' => ContractRole::Superseded, 'superseded_at' => now()]);
        $upgrade->contract->update(['role' => ContractRole::Current, 'superseded_at' => null]);
        $submission->update(['type' => SubmissionType::Pro]);
        $upgrade->update(['status' => PackChangeStatus::Completed, 'applied_at' => now()]);

        Log::channel('payments')->notice('pack_upgrade.applied', [
            'payment' => $payment->getKey(),
            'submission' => $submission->getKey(),
            'pack_change' => $upgrade->getKey(),
        ]);

        return true;
    }
}
