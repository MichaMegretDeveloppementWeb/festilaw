<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationDetailService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Back-office : detail d'une generation, etape par etape (statut, modele, jetons, cout, duree, essais,
 * erreur), avec la relance d'un echec et l'arret d'une generation en cours. L'ecran se rafraichit seul
 * tant que la generation n'est pas terminee. Aucune requete ici.
 */
#[Layout('layouts.admin')]
class GenerationDetail extends Component
{
    use HandlesAdminErrors;

    #[Locked]
    public int $generationId;

    public function mount(int $generation): void
    {
        $this->generationId = $generation;
    }

    public function retry(RetryBlogGenerationAction $retry): void
    {
        try {
            $retry->execute($this->generationId);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin retry blog generation');

            return;
        }

        $this->dispatch('ui-toast', title: __('Génération relancée.'), type: 'success');
    }

    public function cancel(CancelBlogGenerationAction $cancel): void
    {
        try {
            $stoppedNow = $cancel->execute($this->generationId);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin cancel blog generation');

            return;
        }

        $this->dispatch('ui-toast', type: 'success', title: $stoppedNow
            ? __('Génération arrêtée.')
            : __('Arrêt demandé : la génération s\'arrêtera après l\'étape en cours.'));
    }

    public function render(BlogGenerationDetailService $details): View
    {
        $generation = null;

        try {
            $generation = $details->find($this->generationId);
            $this->resetErrorBag('generation-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading an admin blog generation.', ['generation_id' => $this->generationId, 'exception' => $e]);
            $this->addError('generation-load-failed', __('Le chargement de cette génération a échoué. Réessayez ; si le problème persiste, contactez-nous.'));
        }

        return view('livewire.admin.blog.generation-detail', ['generation' => $generation])
            ->title(__('Génération n° :id', ['id' => $this->generationId]));
    }
}
