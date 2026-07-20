<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
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

    public function show(int $id): void
    {
        $this->redirectRoute('admin.submissions.show', ['submission' => $id], navigate: true);
    }

    public function render(): View
    {
        $dossierTypes = [SubmissionType::Starter, SubmissionType::Pro, SubmissionType::Scale];

        $submissions = Submission::query()
            ->when(
                $this->contactsMode,
                fn ($query) => $query->where('type', SubmissionType::Contact),
                fn ($query) => $query->whereIn('type', $dossierTypes),
            )
            ->when(! $this->contactsMode && $this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when(! $this->contactsMode && $this->type !== '', fn ($query) => $query->where('type', $this->type))
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
            ->latest()
            ->paginate(20);

        return view('livewire.admin.submission-list', [
            'submissions' => $submissions,
            'statuses' => SubmissionStatus::cases(),
            'types' => $dossierTypes,
        ])->title(($this->contactsMode ? __('Prises de contact') : __('Dossiers')).' · Back-office Festilaw');
    }
}
