<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Livewire\Concerns\CommandsBlogGenerations;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationDetailService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Back-office : la page d'une generation. Ce qui s'est passe (qui l'a lancee, qui l'a arretee, a quelle etape
 * elle a echoue), ce qu'elle a consomme, le compte rendu de l'IA, puis ses etapes une a une. On y arrive des
 * le lancement : l'ecran se relit seul tant que la generation n'est pas terminee. Un echec s'y relance, une
 * generation en cours s'y arrete. Aucune requete ici.
 */
#[Layout('layouts.admin')]
class GenerationDetail extends Component
{
    use CommandsBlogGenerations;
    use HandlesAdminErrors;

    #[Locked]
    public int $generationId;

    public function mount(int $generation): void
    {
        $this->generationId = $generation;
    }

    public function retry(RetryBlogGenerationAction $retry): void
    {
        $this->relaunchGeneration($retry, $this->generationId);
    }

    public function cancel(CancelBlogGenerationAction $cancel): void
    {
        $this->stopGeneration($cancel, $this->generationId);
    }

    public function render(BlogGenerationDetailService $details): View
    {
        $generation = null;

        try {
            $generation = $details->find($this->generationId);
            $this->resetErrorBag('generation-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading an admin blog generation.', ['generation_id' => $this->generationId, 'exception' => $e]);
            $this->addError('generation-load-failed', __('Le chargement de cette génération a échoué. Réessayez. Si le problème persiste, contactez l\'assistance technique.'));
        }

        return view('livewire.admin.blog.generation-detail', ['generation' => $generation])
            ->title(__('Génération n° :id', ['id' => $this->generationId]))->layoutData(['surface' => 'plain']);
    }
}
