<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Data\Admin\SubmissionListFilters;
use App\Enums\Submission\DossierState;
use App\Enums\Submission\SubmissionType;
use App\Services\Admin\SubmissionListService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Back-office : liste filtrable des dossiers, ou des prises de contact selon la route (une prise de
 * contact n'est pas un dossier). Lecture seule. Aucune requete ici : le composant delegue au
 * SubmissionListService (-> SubmissionListRepository), conforme a l'architecture en couches.
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

    public function render(SubmissionListService $submissions): View
    {
        $result = $submissions->list(
            new SubmissionListFilters(
                contactsMode: $this->contactsMode,
                type: $this->type,
                state: $this->state,
                search: $this->search,
            ),
            perPage: 20,
            currentYear: (int) now()->year,
        );

        return view('livewire.admin.submission-list', [
            'submissions' => $result->submissions,
            'stateFilters' => $this->stateFilterOptions(),
            'types' => [SubmissionType::Starter, SubmissionType::Pro, SubmissionType::Scale],
            'dossierStates' => $result->dossierStates,
        ])->title(($this->contactsMode ? __('Prises de contact') : __('Dossiers')).' · Back-office Festilaw');
    }

    /**
     * Options du filtre d'etat (les etats "A renouveler" et "En retard" sont regroupes en un seul
     * filtre : ils forment le meme ensemble a un instant donne ; la colonne affiche l'etat precis par
     * ligne). Pur affichage, aucune requete.
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
}
