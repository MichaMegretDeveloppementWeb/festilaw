<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Repositories\Blog\OpenRouterKeyRepository;
use Laravel\Ai\AiManager;

/**
 * Donne au SDK la cle OpenRouter enregistree dans les reglages, avant le premier appel a un modele (decision de
 * Micha du 23/11 : la cle quitte le .env). Le SDK lit sa cle dans la configuration ; elle y est posee une fois
 * par commande ou par requete (liaison scoped), et le fournisseur deja construit est oublie pour la reprendre.
 * Sans cle enregistree, la configuration garde la sienne, vide en production : l'appel echoue comme une cle
 * refusee, et la generation le dit.
 */
final class OpenRouterKeyActivator
{
    private bool $activated = false;

    public function __construct(
        private readonly OpenRouterKeyRepository $keys,
        private readonly AiManager $ai,
        private readonly string $provider,
    ) {}

    public function activate(): void
    {
        if ($this->activated) {
            return;
        }

        $this->activated = true;
        $key = $this->keys->current();

        if ($key === null) {
            return;
        }

        config(["ai.providers.{$this->provider}.key" => $key->secret]);
        $this->ai->forgetInstance($this->provider);
    }
}
