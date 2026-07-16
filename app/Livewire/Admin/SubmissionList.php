<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Back-office : liste filtrable des demandes (statut, parcours, recherche). Lecture seule ; le detail
 * et le changement de statut sont sur SubmissionDetail.
 */
#[Layout('layouts.admin')]
#[Title('Dossiers · Back-office Festilaw')]
class SubmissionList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

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
        $submissions = Submission::query()
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
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
            'types' => SubmissionType::cases(),
        ]);
    }
}
