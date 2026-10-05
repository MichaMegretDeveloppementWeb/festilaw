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
 *
 * Retour Pro -> Creator : une demande (justificatif de CA recent + attestation d'eligibilite) que Festilaw
 * valide ; effet au prochain renouvellement (l'annee qui suit la derniere annee payee), ou des la validation
 * si ce renouvellement est deja du. Le mandat Creator se signe alors depuis l'espace, avant de renouveler.
 */
final readonly class PackChangeService
{
    /** Une demande refusee reste affichee au client pendant ce delai (jours). */
    private const REJECTION_NOTICE_DAYS = 30;

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
            && $this->isActiveClient($submission)
            && $this->renewals->dueYear($submission) === null
            && $this->inProgress($submission) === null;
    }

    /** Le client Pro peut-il demander le Creator : client Pro actif, sans changement en cours. */
    public function canRequestDowngrade(Submission $submission): bool
    {
        return $submission->type === SubmissionType::Pro
            && $this->isActiveClient($submission)
            && $this->inProgress($submission) === null;
    }

    /** Annee a partir de laquelle un retour au Creator s'applique : celle qui suit la derniere annee payee. */
    public function downgradeEffectiveYear(Submission $submission): int
    {
        return ($this->renewals->paidThroughYear($submission) ?? (int) now()->year) + 1;
    }

    /**
     * Le mandat que le client doit signer depuis son espace : celui de la montee en cours, ou le mandat en
     * vigueur pas encore signe d'un client actif (nouveau pack applique au renouvellement).
     */
    public function signableContract(Submission $submission): ?Contract
    {
        $upgradeContract = $this->openUpgrade($submission)?->contract;
        if ($upgradeContract !== null) {
            return $upgradeContract->signature_status !== SignatureStatus::Signed ? $upgradeContract : null;
        }

        $current = $submission->contract()->first();

        return $current !== null && $current->signature_status !== SignatureStatus::Signed && $this->isActiveClient($submission)
            ? $current
            : null;
    }

    public function panel(Submission $submission): PackChangePanelData
    {
        $other = $submission->type === SubmissionType::Pro ? SubmissionType::Starter : SubmissionType::Pro;
        $upgrade = $this->openUpgrade($submission);
        $change = $upgrade ?? $this->inProgress($submission);
        $signable = $this->signableContract($submission);

        $mode = match (true) {
            $upgrade !== null => $this->upgradeStep($upgrade),
            $signable !== null => PackChangePanelData::MANDATE_SIGN,
            $change?->status === PackChangeStatus::Requested => PackChangePanelData::DOWNGRADE_REQUESTED,
            $change?->status === PackChangeStatus::Approved => PackChangePanelData::DOWNGRADE_APPROVED,
            $this->canStartUpgrade($submission) => PackChangePanelData::UPGRADE_OFFER,
            $this->canRequestDowngrade($submission) => $this->recentlyRejected($submission)
                ? PackChangePanelData::DOWNGRADE_REJECTED
                : PackChangePanelData::DOWNGRADE_OFFER,
            default => PackChangePanelData::NONE,
        };

        $contract = $upgrade?->contract ?? $signable;

        return new PackChangePanelData(
            mode: $mode,
            currentPackLabel: $submission->type->label(),
            otherPackLabel: $other->label(),
            otherAnnualCents: $other->annualCents(),
            quoteCents: $this->upgradeQuoteCents(),
            year: (int) now()->year,
            signatureStarted: (string) ($contract?->signature_provider_reference ?? '') !== '',
            signatureDeclined: $contract?->signature_status === SignatureStatus::Declined,
            effectiveYear: $change?->effective_year ?? ($mode === PackChangePanelData::DOWNGRADE_OFFER ? $this->downgradeEffectiveYear($submission) : null),
        );
    }

    private function isActiveClient(Submission $submission): bool
    {
        return $submission->status !== SubmissionStatus::Cancelled && $submission->isActive();
    }

    private function recentlyRejected(Submission $submission): bool
    {
        $latest = $submission->packChanges()->first();

        return $latest !== null
            && $latest->status === PackChangeStatus::Rejected
            && $latest->decided_at?->gt(now()->subDays(self::REJECTION_NOTICE_DAYS));
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
