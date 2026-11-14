<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Services\Admin\Blog\Generation\BlogGenerationDetailService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Back-office : carte de suivi en direct de la generation en cours (ou de la prochaine a traiter). Seul
 * composant de l'ecran a se rafraichir tout seul : toutes les deux secondes tant qu'une generation tourne,
 * plus rarement sinon. Deux requetes par rafraichissement.
 *
 * Quand ce qu'elle montre change d'etat (generation prise en charge, terminee, nouvelle generation), elle
 * previent la page, qui relit son journal.
 */
class GenerationProgress extends Component
{
    /** Ce que la carte montrait au rendu precedent (identifiant et statut), pour detecter un changement. */
    public ?string $shown = null;

    /** La page vient de lancer, de relancer ou d'arreter une generation : la carte se relit tout de suite. */
    #[On('blog-generation-changed')]
    public function refreshProgress(): void {}

    public function render(BlogGenerationDetailService $details): View
    {
        $generation = null;

        try {
            $generation = $details->active();
            $this->resetErrorBag('progress-load-failed');
            $this->notifyPageWhenStateChanged($generation === null ? 'none' : $generation->id.':'.$generation->status->value);
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the blog generation progress.', ['exception' => $e]);
            $this->addError('progress-load-failed', __('Le suivi de la génération est momentanément indisponible.'));
        }

        return view('livewire.admin.blog.generation-progress', ['generation' => $generation]);
    }

    private function notifyPageWhenStateChanged(string $state): void
    {
        if ($this->shown !== null && $this->shown !== $state) {
            $this->dispatch('blog-generation-progressed');
        }

        $this->shown = $state;
    }
}
