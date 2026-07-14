<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dernier filet de securite a la frontiere Livewire : toute erreur inattendue (non convertie en
 * exception typee dans les couches basses) est capturee ici, tracee integralement dans les logs, et
 * remplacee par un message generique. L'utilisateur ne voit jamais de 500 ni de detail technique.
 * A placer en dernier catch, apres le catch (BaseAppException) qui porte les messages precis.
 */
trait HandlesUnexpectedErrors
{
    protected function reportUnexpectedError(Throwable $e, string $errorBag = 'form', string $context = 'Livewire component'): void
    {
        Log::error("Unexpected error in {$context}.", ['exception' => $e]);

        $this->addError($errorBag, 'Something went wrong on our end. Please try again. If the problem persists, contact us.');
    }
}
