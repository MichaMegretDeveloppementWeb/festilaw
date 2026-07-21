<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Billing\RenewalStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
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

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    /** Filtre renouvellement : '' (tous) ou 'due' (a renouveler ou en retard). */
    #[Url]
    public string $renewal = '';

    public function mount(): void
    {
        $this->contactsMode = request()->routeIs('admin.contacts.*');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedRenewal(): void
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
            ->when(! $this->contactsMode && $this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when(! $this->contactsMode && $this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when(! $this->contactsMode && $this->renewal === 'due', fn ($query) => $this->scopeNeedsRenewal($query, $currentYear))
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
            'statuses' => SubmissionStatus::cases(),
            'types' => $dossierTypes,
            'renewalBadges' => $this->renewalBadges($submissions->getCollection(), $renewals),
        ])->title(($this->contactsMode ? __('Prises de contact') : __('Dossiers')).' · Back-office Festilaw');
    }

    /**
     * Restreint aux dossiers actifs dont l'abonnement n'est pas paye pour l'annee en cours
     * (a renouveler ou en retard) : au moins un paiement d'abonnement reussi, mais aucun couvrant
     * l'annee courante ou au-dela.
     *
     * @param  Builder<Submission>  $query
     */
    private function scopeNeedsRenewal(Builder $query, int $year): void
    {
        $subscription = PaymentType::subscriptionCases();

        // active() = souscription payee non remboursee + non annule (whereHas succeeded subscription inclus).
        $query->active()
            ->whereDoesntHave('payments', fn ($p) => $p->where('status', PaymentStatus::Succeeded)->whereIn('type', $subscription)->where('service_year', '>=', $year));
    }

    /**
     * Etat de renouvellement (label + severite) par dossier actif, pour le badge de la liste.
     *
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, array{label: string, severity: string}>
     */
    private function renewalBadges($submissions, RenewalService $renewals): array
    {
        $badges = [];

        foreach ($submissions as $submission) {
            if (! $submission->type->hasOnlineJourney() || ! $submission->isActive()) {
                continue;
            }

            $status = $renewals->status($submission);
            $badges[$submission->id] = [
                'label' => $status->label(),
                'severity' => match ($status) {
                    RenewalStatus::UpToDate => 'ok',
                    RenewalStatus::Due => 'warn',
                    RenewalStatus::Overdue => 'bad',
                },
            ];
        }

        return $badges;
    }
}
