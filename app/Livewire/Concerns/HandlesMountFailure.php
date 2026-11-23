<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Filet d'un ecran qui lit ses donnees a l'ouverture : une erreur dans `mount` donnait une page d'erreur 500, et une
 * erreur au rendu une fenetre d'erreur de Livewire (audit du 23/11). Le detail part au journal, et la vue montre a
 * la place du formulaire le bloc d'echec du kit. Son bouton recharge la page, car seule une nouvelle ouverture relit
 * les donnees.
 */
trait HandlesMountFailure
{
    #[Locked]
    public bool $loadFailed = false;

    #[Locked]
    public string $reloadUrl = '';

    /** Appelee par Livewire a l'ouverture, avant le `mount` de l'ecran : l'adresse de la page, pour la recharger. */
    public function mountHandlesMountFailure(): void
    {
        $this->reloadUrl = request()->fullUrl();
    }

    public function reloadPage(): void
    {
        $this->redirect($this->reloadUrl);
    }

    /** @param  array<string, mixed>  $logContext */
    protected function failedToLoad(Throwable $e, string $context, array $logContext = []): void
    {
        Log::error("Unexpected error while loading {$context}.", [...$logContext, 'exception' => $e]);

        $this->loadFailed = true;
    }
}
