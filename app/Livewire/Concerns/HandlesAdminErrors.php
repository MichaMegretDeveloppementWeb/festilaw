<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Filet de securite generique pour les composants du back-office (feedback par toast). Toute erreur
 * inattendue (non convertie en exception typee) est tracee integralement dans les logs et remplacee
 * par un toast d'erreur generique : l'admin ne voit jamais de 500 ni de detail technique. A placer en
 * dernier catch, apres le(s) catch typs eventuels.
 */
trait HandlesAdminErrors
{
    protected function reportAdminError(Throwable $e, string $context): void
    {
        Log::error("Unexpected error in {$context}.", ['exception' => $e]);

        $this->dispatch('admin-toast', message: __('Une erreur inattendue est survenue. Réessayez ; si le problème persiste, contactez-nous.'), type: 'error');
    }
}
