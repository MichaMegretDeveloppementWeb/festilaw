<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Anti-spam leger pour les formulaires publics Livewire : honeypot + limite par IP.
 * Aucune infrastructure requise.
 */
trait HasSpamProtection
{
    /** Honeypot : doit rester vide. Un bot le remplit. */
    public string $hp = '';

    protected function looksLikeSpam(): bool
    {
        return trim($this->hp) !== '';
    }

    /** Limite par IP. Renvoie true (bloque) et ajoute une erreur de formulaire si depasse. */
    protected function tooManyAttempts(string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $throttleKey = $key.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $this->addError('form', __('Too many attempts. Please wait a minute and try again.'));

            return true;
        }

        RateLimiter::hit($throttleKey, $decaySeconds);

        return false;
    }
}
