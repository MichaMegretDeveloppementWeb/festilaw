<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\RenewalStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\DossierState;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Payment;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Suivi du renouvellement annuel, entierement derive des paiements (aucun statut stocke). Une annee de
 * service court du 1er janvier au 31 decembre ; la 1re annee est au prorata, chaque annee suivante est
 * facturee au plein tarif en janvier. Le client dispose d'une fenetre de grace pour regler.
 */
final class RenewalService
{
    /** Annee de service la plus recente couverte par un paiement d'abonnement reussi, ou null. */
    public function paidThroughYear(Submission $submission): ?int
    {
        $submission->loadMissing('payments');

        $years = $submission->payments
            ->filter(fn (Payment $p): bool => $p->status === PaymentStatus::Succeeded && $p->type->isSubscription())
            ->map(fn (Payment $p): ?int => $p->service_year)
            ->filter();

        return $years->isEmpty() ? null : (int) $years->max();
    }

    /**
     * Etat AFFICHE du dossier, entierement derive (statut de workflow + paiements + renouvellement). C'est
     * l'etat unique montre et filtre au back-office et cote client, a la place du « Payé » brut.
     */
    public function state(Submission $submission, ?CarbonInterface $now = null): DossierState
    {
        if ($submission->status === SubmissionStatus::Cancelled) {
            return DossierState::Cancelled;
        }

        // Pas encore actif (aucun abonnement paye non rembourse) : le dossier est "en cours".
        if (! $submission->isActive()) {
            return DossierState::InProgress;
        }

        // "Prestation livree" (RP delivree -> statut Completed) et "abonnement a renouveler" sont deux
        // axes ORTHOGONAUX : un dossier termine continue de se renouveler chaque annee. On derive donc le
        // renouvellement EN PREMIER ; "Termine" ne s'affiche que lorsque le dossier est a jour · sinon il
        // doit rester visible en "a renouveler / en retard" (sans quoi un client servi qui doit renouveler
        // disparaitrait des vues operationnelles alors que ProcessRenewals le relance).
        return match ($this->status($submission, $now)) {
            RenewalStatus::Due => DossierState::RenewalDue,
            RenewalStatus::Overdue => DossierState::RenewalOverdue,
            RenewalStatus::UpToDate => $submission->status === SubmissionStatus::Completed
                ? DossierState::Completed
                : DossierState::Active,
        };
    }

    /**
     * Annee dont le renouvellement est du (annee de service courante non encore couverte), ou null si le
     * dossier est a jour. Retourne null pour un dossier jamais paye (c'est le parcours initial, pas un
     * renouvellement).
     */
    public function dueYear(Submission $submission, ?CarbonInterface $now = null): ?int
    {
        $now = $this->normalise($now);
        $paidThrough = $this->paidThroughYear($submission);

        if ($paidThrough === null) {
            return null;
        }

        return $paidThrough < $now->year ? $now->year : null;
    }

    /** Etat de renouvellement a la date donnee (a jour / a renouveler / en retard). */
    public function status(Submission $submission, ?CarbonInterface $now = null): RenewalStatus
    {
        $now = $this->normalise($now);
        $dueYear = $this->dueYear($submission, $now);

        if ($dueYear === null) {
            return RenewalStatus::UpToDate;
        }

        return $now->greaterThan($this->graceEndsAt($dueYear))
            ? RenewalStatus::Overdue
            : RenewalStatus::Due;
    }

    /** Date du prochain renouvellement : 1er janvier suivant l'annee couverte (null si jamais paye). */
    public function nextRenewalDate(Submission $submission): ?CarbonImmutable
    {
        $paidThrough = $this->paidThroughYear($submission);

        return $paidThrough === null ? null : CarbonImmutable::create($paidThrough + 1, 1, 1);
    }

    /** Fin de la fenetre de grace pour l'annee donnee : 1er janvier + grace_days. */
    public function graceEndsAt(int $year): CarbonImmutable
    {
        return CarbonImmutable::create($year, 1, 1)->addDays($this->graceDays());
    }

    private function normalise(?CarbonInterface $now): CarbonImmutable
    {
        return $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();
    }

    private function graceDays(): int
    {
        return (int) config('festilaw.renewal.grace_days', 30);
    }
}
