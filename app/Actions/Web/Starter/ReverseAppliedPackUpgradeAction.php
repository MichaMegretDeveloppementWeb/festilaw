<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\ContractRole;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\PackChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Defait un passage au Pro dont la difference est remboursee (SC12), que le remboursement vienne du bouton du
 * back-office (RevertPackUpgradeAction) ou du tableau de bord Stripe (webhook charge.refunded, via
 * MarkPaymentRefundedAction) : le dossier redevient Creator, son dernier mandat Creator signe reprend effet
 * et le mandat Pro est conserve (remplace). La montee passe en "annulee et remboursee".
 *
 * Une seule fois par montee (sans effet si elle n'est plus "effectuee"). Si le dossier n'est deja plus en Pro
 * (retour au Creator applique depuis), seul le statut de la montee change : les mandats restent tels quels.
 */
final readonly class ReverseAppliedPackUpgradeAction
{
    /** @return bool Whether the upgrade was reversed by this call. */
    public function execute(PackChange $upgrade, ?int $adminId = null): bool
    {
        $reversed = DB::transaction(function () use ($upgrade, $adminId): bool {
            $upgrade = PackChange::query()->whereKey($upgrade->getKey())->lockForUpdate()->first();
            if ($upgrade === null || ! $upgrade->isUpgrade() || $upgrade->status !== PackChangeStatus::Completed) {
                return false;
            }

            $submission = $upgrade->submission;

            if ($submission->type === SubmissionType::Pro) {
                $submission->contracts()->reorder()
                    ->where('role', ContractRole::Current)
                    ->update(['role' => ContractRole::Superseded, 'superseded_at' => now()]);

                $submission->contracts()->reorder()
                    ->where('pack', SubmissionType::Starter)
                    ->where('role', ContractRole::Superseded)
                    ->whereNotNull('signed_at')
                    ->latest('id')
                    ->first()
                    ?->update(['role' => ContractRole::Current, 'superseded_at' => null]);

                $submission->update(['type' => SubmissionType::Starter]);
            }

            $upgrade->update([
                'status' => PackChangeStatus::Reverted,
                'decided_by' => $adminId,
                'decided_at' => now(),
            ]);

            return true;
        });

        if ($reversed) {
            Log::channel('payments')->notice('pack_upgrade.reverted', [
                'pack_change' => $upgrade->getKey(),
                'submission' => $upgrade->submission_id,
                'payment' => $upgrade->payment_id,
                'admin' => $adminId,
            ]);
        }

        return $reversed;
    }
}
