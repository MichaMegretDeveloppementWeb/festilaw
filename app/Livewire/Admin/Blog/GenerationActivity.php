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
 * Back-office : tuile « En cours » de la vue d'ensemble. Elle dit s'il y a une generation en attente ou en
 * cours, a quelle etape, et mene a sa page. Sa taille ne change jamais, qu'il y ait une generation ou non :
 * rien n'apparait ni ne pousse la page.
 *
 * Seul element de la vue d'ensemble a se relire tout seul : toutes les deux secondes tant qu'une generation
 * tourne, plus rarement sinon. Deux requetes par relecture. Quand ce qu'elle montre change d'etat, elle
 * previent la page, qui relit ses dernieres generations.
 */
class GenerationActivity extends Component
{
    /** Ce que la tuile montrait au rendu precedent (identifiant et statut), pour detecter un changement. */
    public ?string $shown = null;

    /** La page vient de relancer une generation : la tuile se relit tout de suite. */
    #[On('blog-generation-changed')]
    public function refreshActivity(): void {}

    public function render(BlogGenerationDetailService $details): View
    {
        $generation = null;

        try {
            $generation = $details->active();
            $this->resetErrorBag('activity-load-failed');
            $this->notifyPageWhenStateChanged($generation === null ? 'none' : $generation->id.':'.$generation->status->value);
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the blog generation activity.', ['exception' => $e]);
            $this->addError('activity-load-failed', __('Suivi momentanément indisponible'));
        }

        return view('livewire.admin.blog.generation-activity', ['generation' => $generation]);
    }

    private function notifyPageWhenStateChanged(string $state): void
    {
        if ($this->shown !== null && $this->shown !== $state) {
            $this->dispatch('blog-generation-progressed');
        }

        $this->shown = $state;
    }
}
