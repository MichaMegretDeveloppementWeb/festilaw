<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Actions\Blog\Generation\CancelBlogGenerationAction;
use App\Actions\Blog\Generation\RetryBlogGenerationAction;
use App\Exceptions\BaseAppException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Relancer et arreter une generation depuis un ecran du back-office : meme traitement des refus et meme
 * message de retour sur la vue d'ensemble, l'historique et la page d'une generation. Le composant qui
 * l'emploie emploie aussi HandlesAdminErrors (dernier filet).
 */
trait CommandsBlogGenerations
{
    abstract protected function reportAdminError(Throwable $e, string $context): void;

    /** @return bool vrai si la generation est repartie */
    protected function relaunchGeneration(RetryBlogGenerationAction $retry, int $generationId): bool
    {
        try {
            $retry->execute($generationId);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return false;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin retry blog generation');

            return false;
        }

        $this->dispatch('ui-toast', title: __('Génération relancée.'), type: 'success');

        return true;
    }

    /** @return bool vrai si l'arret est pris en compte (tout de suite, ou apres l'etape en cours) */
    protected function stopGeneration(CancelBlogGenerationAction $cancel, int $generationId): bool
    {
        try {
            $stoppedNow = $cancel->execute($generationId, auth()->id());
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return false;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin cancel blog generation');

            return false;
        }

        $this->dispatch('ui-toast', type: 'success', title: $stoppedNow
            ? __('Génération arrêtée.')
            : __('La génération s\'arrêtera après l\'étape en cours.'));

        return true;
    }
}
