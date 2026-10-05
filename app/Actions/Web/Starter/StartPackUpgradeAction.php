<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Starter\StarterException;
use App\Mail\FunnelNotification;
use App\Models\PackChange;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Le client Creator (deja paye, a jour) demande a passer au Pro depuis son espace (SC12) : ouvre la montee et
 * prepare le mandat Pro a signer (en attente, pre-rempli des informations du mandat en vigueur). Rien ne change
 * encore pour le dossier : il reste Creator jusqu'au paiement de la difference (ApplyPackUpgradeAction).
 * Festilaw est prevenu. Idempotent : un double clic retrouve la montee deja ouverte.
 */
final readonly class StartPackUpgradeAction
{
    public function __construct(
        private PackChangeService $packChanges,
        private TeamNotifier $teamNotifier,
    ) {}

    public function execute(Submission $submission): PackChange
    {
        // Meme verrou que les paiements du dossier : une montee ne s'ouvre pas pendant un checkout.
        $upgrade = Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($submission): PackChange {
            $submission->refresh();

            $open = $this->packChanges->openUpgrade($submission);
            if ($open !== null) {
                return $open;
            }

            if (! $this->packChanges->canStartUpgrade($submission)) {
                throw StarterException::packUpgradeUnavailable($submission->id, 'not an up-to-date active Creator client, or a change is already in progress');
            }

            return DB::transaction(function () use ($submission): PackChange {
                $contract = $submission->contracts()->create([
                    'pack' => SubmissionType::Pro,
                    'role' => ContractRole::Pending,
                    'signature_status' => SignatureStatus::Pending,
                    'filled_fields' => $submission->contract?->filled_fields ?? [],
                ]);

                return $submission->packChanges()->create([
                    'from_pack' => SubmissionType::Starter,
                    'to_pack' => SubmissionType::Pro,
                    'status' => PackChangeStatus::Open,
                    'contract_id' => $contract->id,
                    'amount_cents' => $this->packChanges->upgradeQuoteCents(),
                ]);
            });
        });

        if ($upgrade->wasRecentlyCreated) {
            $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::PackUpgradeStarted));
        }

        return $upgrade;
    }
}
