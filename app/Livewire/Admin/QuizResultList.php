<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Quiz\QuizOutcome;
use App\Models\QuizResult;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Back-office : suivi (lecture seule) des reponses anonymes au quiz d'eligibilite public. */
#[Layout('layouts.admin')]
class QuizResultList extends Component
{
    use WithPagination;

    #[Url]
    public string $outcome = '';

    public function updatedOutcome(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $results = QuizResult::query()
            ->when($this->outcome !== '', fn ($query) => $query->where('outcome', $this->outcome))
            ->latest()
            ->paginate(20);

        return view('livewire.admin.quiz-result-list', [
            'results' => $results,
            'outcomes' => QuizOutcome::cases(),
            'counts' => [
                QuizOutcome::Concerned->value => QuizResult::where('outcome', QuizOutcome::Concerned)->count(),
                QuizOutcome::Excluded->value => QuizResult::where('outcome', QuizOutcome::Excluded)->count(),
                QuizOutcome::NotConcerned->value => QuizResult::where('outcome', QuizOutcome::NotConcerned)->count(),
            ],
        ])->title(__('Quiz').' · Back-office Festilaw');
    }
}
