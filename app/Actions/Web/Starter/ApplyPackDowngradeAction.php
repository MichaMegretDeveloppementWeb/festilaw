<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\PackChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applique un retour au Creator valide par Festilaw, au renouvellement (SC12) : le dossier passe au Creator
 * (le renouvellement est alors facture au tarif Creator) et un nouveau mandat Creator entre en vigueur, a
 * signer depuis l'espace avant de renouveler. L'ancien mandat Pro est conserve (remplace). Appelee par
 * festilaw:process-renewals des que l'annee d'effet est arrivee, ou a la validation si le renouvellement
 * est deja du. Sans effet sur une demande qui n'est plus "validee".
 */
final readonly class ApplyPackDowngradeAction
{
    /** @return bool Whether the switch was applied by this call. */
    public function execute(PackChange $downgrade): bool
    {
        return DB::transaction(function () use ($downgrade): bool {
            $downgrade = PackChange::query()->whereKey($downgrade->getKey())->lockForUpdate()->first();
            if ($downgrade === null || $downgrade->status !== PackChangeStatus::Approved || $downgrade->isUpgrade()) {
                return false;
            }

            $submission = $downgrade->submission;
            $previous = $submission->contract;

            $submission->contracts()->reorder()
                ->where('role', ContractRole::Current)
                ->update(['role' => ContractRole::Superseded, 'superseded_at' => now()]);

            $contract = $submission->contracts()->create([
                'pack' => SubmissionType::Starter,
                'role' => ContractRole::Current,
                'signature_status' => SignatureStatus::Pending,
                'filled_fields' => $previous?->filled_fields ?? [],
            ]);

            $submission->update(['type' => SubmissionType::Starter]);
            $downgrade->update([
                'status' => PackChangeStatus::Applied,
                'contract_id' => $contract->id,
                'applied_at' => now(),
            ]);

            Log::channel('payments')->notice('pack_downgrade.applied', [
                'pack_change' => $downgrade->id,
                'submission' => $submission->id,
                'effective_year' => $downgrade->effective_year,
            ]);

            return true;
        });
    }
}
