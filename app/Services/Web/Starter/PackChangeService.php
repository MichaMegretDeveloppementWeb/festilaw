<?php

declare(strict_types=1);

namespace App\Services\Web\Starter;

use App\Data\Web\Starter\PackChangePanelData;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Contract;
use App\Models\PackChange;
use App\Models\Submission;
use App\Services\Billing\AnnualFeeProrator;
use App\Services\Billing\RenewalService;
use Carbon\CarbonInterface;

/**
 * Changement de pack d'un dossier deja paye (SC12) : ce que le client peut faire, ce qui est en cours, et le
 * montant d'une montee. Derive des faits (statut du dossier, renouvellement, demandes, mandats, paiements) ;
 * les ecritures sont dans les Actions.
 *
 * Montee Creator -> Pro : le client seul, s'il est a jour (aucun renouvellement du). Il signe un mandat Pro,
 * puis paie l'ecart de tarif au prorata des mois restants de l'annee (mois en cours compris, comme l'annee 1).
 */
final readonly class PackChangeService
{
    public function __construct(
        private AnnualFeeProrator $prorator,
        private RenewalService $renewals,
    ) {}

    /** Le changement encore en cours sur le dossier (un seul a la fois), s'il y en a un. */
    public function inProgress(Submission $submission): ?PackChange
    {
        return $submission->packChanges()->whereIn('status', PackChangeStatus::inProgress())->first();
    }

    /** La montee vers le Pro en cours (mandat a signer ou difference a payer), s'il y en a une. */
    public function openUpgrade(Submission $submission): ?PackChange
    {
        return $submission->packChanges()->where('status', PackChangeStatus::Open)->with('contract')->first();
    }

    /** Montant du passage au Pro a la date donnee : ecart de tarif au prorata des mois restants de l'annee. */
    public function upgradeQuoteCents(?CarbonInterface $at = null): int
    {
        return $this->prorator->firstYearCents(
            SubmissionType::Pro->annualCents() - SubmissionType::Starter->annualCents(),
            $at ?? now(),
        );
    }

    /** Le client peut-il passer au Pro maintenant : client Creator actif, a jour, sans changement en cours. */
    public function canStartUpgrade(Submission $submission): bool
    {
        return $submission->type === SubmissionType::Starter
            && $submission->status !== SubmissionStatus::Cancelled
            && $submission->isActive()
            && $this->renewals->dueYear($submission) === null
            && $this->inProgress($submission) === null;
    }

    /** Le mandat que le client doit signer depuis son espace (celui de la montee en cours), s'il y en a un. */
    public function signableContract(Submission $submission): ?Contract
    {
        $contract = $this->openUpgrade($submission)?->contract;

        return $contract !== null && $contract->signature_status !== SignatureStatus::Signed ? $contract : null;
    }

    public function panel(Submission $submission): PackChangePanelData
    {
        $other = $submission->type === SubmissionType::Pro ? SubmissionType::Starter : SubmissionType::Pro;
        $upgrade = $this->openUpgrade($submission);

        $mode = match (true) {
            $upgrade !== null => $this->upgradeStep($upgrade),
            $this->canStartUpgrade($submission) => PackChangePanelData::UPGRADE_OFFER,
            default => PackChangePanelData::NONE,
        };

        $contract = $upgrade?->contract;

        return new PackChangePanelData(
            mode: $mode,
            currentPackLabel: $submission->type->label(),
            otherPackLabel: $other->label(),
            otherAnnualCents: $other->annualCents(),
            quoteCents: $this->upgradeQuoteCents(),
            year: (int) now()->year,
            signatureStarted: (string) ($contract?->signature_provider_reference ?? '') !== '',
            signatureDeclined: $contract?->signature_status === SignatureStatus::Declined,
        );
    }

    private function upgradeStep(PackChange $upgrade): string
    {
        if ($upgrade->contract?->signature_status !== SignatureStatus::Signed) {
            return PackChangePanelData::UPGRADE_SIGN;
        }

        $processing = $upgrade->submission->payments()
            ->where('type', PaymentType::PackUpgrade)
            ->where('status', PaymentStatus::Processing)
            ->exists();

        return $processing ? PackChangePanelData::UPGRADE_PROCESSING : PackChangePanelData::UPGRADE_PAY;
    }
}
