<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Quiz\QuizOutcome;
use App\Services\Admin\QuizResultListService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Back-office : suivi (lecture seule) des reponses anonymes au quiz d'eligibilite public. Aucune requete
 * ici : la lecture passe par QuizResultListService (-> QuizResultListRepository).
 */
#[Layout('layouts.admin')]
class QuizResultList extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    /** '' (toutes) ou une valeur de QuizOutcome. */
    #[Url]
    public string $outcome = '';

    public function updatedOutcome(): void
    {
        $this->resetPage();
    }

    public function render(QuizResultListService $quiz): View
    {
        $result = null;

        try {
            // Une valeur inconnue dans l'adresse ne casse rien : le filtre est ignore.
            $result = $quiz->list(QuizOutcome::tryFrom($this->outcome), self::PER_PAGE);
            $this->resetErrorBag('quiz-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin quiz result list.', ['exception' => $e]);
            $this->addError('quiz-load-failed', __('Le chargement des réponses au quiz a échoué.'));
        }

        return view('livewire.admin.quiz-result-list', [
            'results' => $result?->results,
            'outcomes' => QuizOutcome::cases(),
            'counts' => $result?->counts ?? [],
        ])->title(__('Quiz'))->layoutData(['surface' => 'plain']);
    }
}
