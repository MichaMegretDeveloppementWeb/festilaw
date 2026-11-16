<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Livewire\Concerns\CommandsBlogGenerations;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationOverviewService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Back-office : vue d'ensemble de la generation par IA. Depense du mois, generation automatique, brouillons
 * a relire, generation en cours et dernieres generations. Le lancement (GenerationLauncher), l'historique
 * (GenerationHistory) et les reglages (GenerationSettings) ont chacun leur place.
 *
 * Seule la tuile « En cours » (GenerationActivity) se relit toute seule ; elle previent cette page quand une
 * generation change d'etat. Aucune requete ici : la lecture passe par le Service, la relance par son Action.
 */
#[Layout('layouts.admin')]
class GenerationDashboard extends Component
{
    use CommandsBlogGenerations;
    use HandlesAdminErrors;

    public function retry(int $generationId, RetryBlogGenerationAction $retry): void
    {
        if ($this->relaunchGeneration($retry, $generationId)) {
            $this->dispatch('blog-generation-changed');
        }
    }

    /** La tuile « En cours » signale qu'une generation a change d'etat : la page se relit. */
    #[On('blog-generation-progressed')]
    public function refreshOverview(): void {}

    public function render(BlogGenerationOverviewService $overviews): View
    {
        $overview = null;

        try {
            $overview = $overviews->overview();
            $this->resetErrorBag('generation-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog generation overview.', ['exception' => $e]);
            $this->addError('generation-load-failed', __('Le chargement de la génération par IA a échoué. Réessayez. Si le problème persiste, contactez l\'assistance technique.'));
        }

        return view('livewire.admin.blog.generation-dashboard', ['overview' => $overview])->title(__('Génération IA'))->layoutData(['surface' => 'plain']);
    }
}
