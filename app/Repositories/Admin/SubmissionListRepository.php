<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Data\Admin\SubmissionListFilters;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\DossierState;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Acces BDD (lecture) de la liste back-office : dossiers a parcours ou prises de contact, filtres par
 * type / etat derive / recherche, paginee.
 *
 * Le partitionnement d'etat est le MIROIR SQL de RenewalService::state() (contrat de coherence, verifie
 * par un test d'equivalence) : le filtre et le badge d'une ligne doivent toujours designer le meme
 * ensemble. "Prestation livree" (statut Completed) et "abonnement a renouveler" sont orthogonaux : un
 * dossier termine mais du/en retard reste dans "renewal", jamais masque par "Termine".
 */
final readonly class SubmissionListRepository
{
    /** @var list<SubmissionType> */
    private const DOSSIER_TYPES = [SubmissionType::Starter, SubmissionType::Pro, SubmissionType::Scale];

    /**
     * @return LengthAwarePaginator<Submission>
     */
    public function paginate(SubmissionListFilters $filters, int $perPage, int $currentYear): LengthAwarePaginator
    {
        return Submission::query()
            ->when(
                $filters->contactsMode,
                fn (Builder $query) => $query->where('type', SubmissionType::Contact),
                fn (Builder $query) => $query->whereIn('type', self::DOSSIER_TYPES),
            )
            ->when(! $filters->contactsMode && $filters->type !== '', fn (Builder $query) => $query->where('type', $filters->type))
            ->when(! $filters->contactsMode && $filters->state !== '', fn (Builder $query) => $this->applyState($query, $filters->state, $currentYear))
            ->when($filters->search !== '', fn (Builder $query) => $this->applySearch($query, $filters->search))
            ->with('payments')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Restreint la requete a l'etat derive demande. MEMES regles que RenewalService::state() :
     *  - Active     = abonnement paye couvrant l'annee, dossier NON termine ;
     *  - renewal    = actif mais annee courante non couverte (INCLUT les dossiers termines dus/en
     *                 retard : "Termine" ne masque jamais le renouvellement) ;
     *  - Completed  = termine ET a jour (un termine du part sous "renewal") ;
     *  - InProgress = pas encore actif (aucun abonnement paye), non annule.
     *
     * @param  Builder<Submission>  $query
     */
    private function applyState(Builder $query, string $state, int $year): void
    {
        $subscription = PaymentType::subscriptionCases();
        // service_year fait autorite pour l'annee couverte (cf. RenewalService::paidThroughYear).
        $coversYear = fn ($p) => $p->where('status', PaymentStatus::Succeeded)->whereIn('type', $subscription)->where('service_year', '>=', $year);
        $hasSucceededSubscription = fn ($p) => $p->where('status', PaymentStatus::Succeeded)->whereIn('type', $subscription);

        match ($state) {
            DossierState::InProgress->value => $query
                ->where('status', '!=', SubmissionStatus::Cancelled)
                ->whereDoesntHave('payments', $hasSucceededSubscription),
            DossierState::Active->value => $query->active()->where('status', '!=', SubmissionStatus::Completed)->whereHas('payments', $coversYear),
            'renewal' => $query->active()->whereDoesntHave('payments', $coversYear),
            DossierState::Completed->value => $query->where('status', SubmissionStatus::Completed)->whereHas('payments', $coversYear),
            DossierState::Cancelled->value => $query->where('status', SubmissionStatus::Cancelled),
            default => null,
        };
    }

    /** @param  Builder<Submission>  $query */
    private function applySearch(Builder $query, string $search): void
    {
        $term = '%'.$search.'%';

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('email', 'like', $term)
                ->orWhere('company_name', 'like', $term)
                ->orWhere('reference', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term);
        });
    }
}
