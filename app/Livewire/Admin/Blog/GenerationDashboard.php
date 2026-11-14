<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationJournalService;
use App\Services\Admin\Blog\Generation\BlogGenerationOverviewService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Back-office : generation d'articles par IA. Depense du mois, lancement d'une generation, journal. La carte
 * de suivi en direct est un composant a part (GenerationProgress), seul a se rafraichir toutes les deux
 * secondes. Aucune requete ici : la lecture passe par les Services, chaque geste par une Action.
 */
#[Layout('layouts.admin')]
class GenerationDashboard extends Component
{
    use HandlesAdminErrors;
    use WithPagination;

    public const PER_PAGE = 15;

    /** Sujet impose pour la prochaine generation (facultatif). */
    public string $topicHint = '';

    /** Consignes pour la prochaine generation (facultatif). */
    public string $instructions = '';

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'topicHint' => ['nullable', 'string', 'max:300'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'topicHint.max' => __('Le sujet ne doit pas dépasser 300 caractères.'),
            'instructions.max' => __('Les consignes ne doivent pas dépasser 2 000 caractères.'),
        ];
    }

    public function launch(QueueBlogGenerationAction $queue): void
    {
        $validated = $this->validate();

        try {
            $queue->execute(new QueueBlogGenerationData(
                topicHint: $validated['topicHint'] ?? null,
                instructions: $validated['instructions'] ?? null,
                requestedBy: auth()->id(),
            ));
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin queue blog generation');

            return;
        }

        $this->reset('topicHint', 'instructions');
        $this->resetPage();
        $this->dispatch('blog-generation-changed');
        $this->dispatch('ui-toast', title: __('Génération demandée.'), type: 'success');
    }

    public function retry(int $generationId, RetryBlogGenerationAction $retry): void
    {
        try {
            $retry->execute($generationId);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin retry blog generation');

            return;
        }

        $this->dispatch('blog-generation-changed');
        $this->dispatch('ui-toast', title: __('Génération relancée.'), type: 'success');
    }

    public function cancel(int $generationId, CancelBlogGenerationAction $cancel): void
    {
        try {
            $stoppedNow = $cancel->execute($generationId);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin cancel blog generation');

            return;
        }

        $this->dispatch('blog-generation-changed');
        $this->dispatch('ui-toast', type: 'success', title: $stoppedNow
            ? __('Génération arrêtée.')
            : __('La génération s\'arrêtera après l\'étape en cours.'));
    }

    /** La carte de suivi signale qu'une generation a change d'etat : le journal se relit. */
    #[On('blog-generation-progressed')]
    public function refreshJournal(): void {}

    public function render(BlogGenerationOverviewService $overviews, BlogGenerationJournalService $journal): View
    {
        $overview = null;
        $generations = null;

        try {
            $overview = $overviews->overview();
            $generations = $journal->page(self::PER_PAGE);
            $this->resetErrorBag('generation-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog generation dashboard.', ['exception' => $e]);
            $this->addError('generation-load-failed', __('Le chargement de la génération par IA a échoué. Réessayez. Si le problème persiste, contactez l\'assistance technique.'));
        }

        return view('livewire.admin.blog.generation-dashboard', [
            'overview' => $overview,
            'generations' => $generations,
        ])->title(__('Génération IA'));
    }
}
