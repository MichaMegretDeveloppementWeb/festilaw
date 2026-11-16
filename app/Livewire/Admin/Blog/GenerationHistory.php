<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Livewire\Concerns\CommandsBlogGenerations;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationJournalService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Back-office : historique des generations, de la plus recente a la plus ancienne. Chaque ligne mene a la
 * page de sa generation ; un echec se relance et une generation en attente ou en cours s'arrete d'ici.
 * Aucune requete ici : la lecture passe par le Service, chaque geste par une Action.
 */
#[Layout('layouts.admin')]
class GenerationHistory extends Component
{
    use CommandsBlogGenerations;
    use HandlesAdminErrors;
    use WithPagination;

    public const PER_PAGE = 20;

    public function retry(int $generationId, RetryBlogGenerationAction $retry): void
    {
        $this->relaunchGeneration($retry, $generationId);
    }

    public function cancel(int $generationId, CancelBlogGenerationAction $cancel): void
    {
        $this->stopGeneration($cancel, $generationId);
    }

    public function render(BlogGenerationJournalService $journal): View
    {
        $generations = null;

        try {
            $generations = $journal->page(self::PER_PAGE);
            $this->resetErrorBag('history-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog generation history.', ['exception' => $e]);
            $this->addError('history-load-failed', __('Le chargement de l\'historique a échoué. Réessayez. Si le problème persiste, contactez l\'assistance technique.'));
        }

        return view('livewire.admin.blog.generation-history', ['generations' => $generations])->title(__('Historique des générations'));
    }
}
