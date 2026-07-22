<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\DossierState;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use App\Services\Billing\RenewalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Back-office : liste filtrable des dossiers, ou des prises de contact selon la route
 * (une prise de contact n'est pas un dossier). Lecture seule.
 */
#[Layout('layouts.admin')]
class SubmissionList extends Component
{
    use WithPagination;

    /** Mode "prises de contact" plutot que "dossiers", fixe selon la route au montage. */
    public bool $contactsMode = false;

    #[Url]
    public string $search = '';

    /** Filtre etat de dossier DERIVE : '' (tous) ou une valeur de stateFilterOptions(). */
    #[Url]
    public string $state = '';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        $this->contactsMode = request()->routeIs('admin.contacts.*');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function show(int $id): void
    {
        $this->redirectRoute('admin.submissions.show', ['submission' => $id], navigate: true);
    }

    public function render(RenewalService $renewals): View
    {
        $dossierTypes = [SubmissionType::Starter, SubmissionType::Pro, SubmissionType::Scale];
        $currentYear = (int) now()->year;

        $submissions = Submission::query()
            ->when(
                $this->contactsMode,
                fn ($query) => $query->where('type', SubmissionType::Contact),
                fn ($query) => $query->whereIn('type', $dossierTypes),
            )
            ->when(! $this->contactsMode && $this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when(! $this->contactsMode && $this->state !== '', fn ($query) => $this->scopeState($query, $this->state, $currentYear))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('email', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->with('payments')
            ->latest()
            ->paginate(20);

        return view('livewire.admin.submission-list', [
            'submissions' => $submissions,
            'stateFilters' => $this->stateFilterOptions(),
            'types' => $dossierTypes,
            'dossierStates' => $this->dossierStates($submissions->getCollection(), $renewals),
        ])->title(($this->contactsMode ? __('Prises de contact') : __('Dossiers')).' · Back-office Festilaw');
    }

    /**
     * Options du filtre d'etat (les etats "A renouveler" et "En retard" sont regroupes en un seul filtre :
     * ils forment le meme ensemble a un instant donne ; la colonne affiche l'etat precis par ligne).
     *
     * @return array<string, string>
     */
    private function stateFilterOptions(): array
    {
        return [
            DossierState::InProgress->value => DossierState::InProgress->label(),
            DossierState::Active->value => DossierState::Active->label(),
            'renewal' => __('À renouveler ou en retard'),
            DossierState::Completed->value => DossierState::Completed->label(),
            DossierState::Cancelled->value => DossierState::Cancelled->label(),
        ];
    }

    /**
     * Restreint la requete a l'etat derive demande, avec les MEMES regles que RenewalService (coherence
     * filtre / colonne). "Actif" = souscription payee couvrant l'annee courante ; "A renouveler/en retard"
     * = active mais annee courante non couverte ; "En cours" = pas encore active.
     *
     * @param  Builder<Submission>  $query
     */
    private function scopeState(Builder $query, string $state, int $year): void
    {
        $subscription = PaymentType::subscriptionCases();
        // Meme regle que RenewalService::paidThroughYear (service_year fait autorite pour l'annee couverte).
        $coversYear = fn ($p) => $p->where('status', PaymentStatus::Succeeded)->whereIn('type', $subscription)->where('service_year', '>=', $year);

        match ($state) {
            DossierState::InProgress->value => $query
                ->whereNotIn('status', [SubmissionStatus::Completed, SubmissionStatus::Cancelled])
                ->whereDoesntHave('payments', fn ($p) => $p->where('status', PaymentStatus::Succeeded)->whereIn('type', $subscription)),
            DossierState::Active->value => $query->active()->where('status', '!=', SubmissionStatus::Completed)->whereHas('payments', $coversYear),
            'renewal' => $this->scopeNeedsRenewal($query, $year),
            DossierState::Completed->value => $query->where('status', SubmissionStatus::Completed),
            DossierState::Cancelled->value => $query->where('status', SubmissionStatus::Cancelled),
            default => null,
        };
    }

    /**
     * Dossiers actifs dont l'abonnement n'est pas paye pour l'annee courante (a renouveler ou en retard) :
     * une souscription payee non remboursee, mais aucune couvrant l'annee courante ou au-dela.
     *
     * @param  Builder<Submission>  $query
     */
    private function scopeNeedsRenewal(Builder $query, int $year): void
    {
        $subscription = PaymentType::subscriptionCases();

        $query->active()
            ->where('status', '!=', SubmissionStatus::Completed) // un dossier termine a son propre etat (badge "Termine"), jamais "a renouveler"
            ->whereDoesntHave('payments', fn ($p) => $p->where('status', PaymentStatus::Succeeded)->whereIn('type', $subscription)->where('service_year', '>=', $year));
    }

    /**
     * Etat derive (label + severite) par ligne, pour la colonne de la liste.
     *
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, DossierState>
     */
    private function dossierStates($submissions, RenewalService $renewals): array
    {
        $states = [];

        foreach ($submissions as $submission) {
            $states[$submission->id] = $renewals->state($submission);
        }

        return $states;
    }
}
