<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Data\Admin\Blog\Generation\OpenRouterKeyState;
use App\Data\Blog\Generation\Health\BlogGenerationHealth;
use App\Data\Blog\Generation\OpenRouterKey;
use App\Enums\Blog\Generation\OpenRouterKeyCheck;
use App\Repositories\Blog\BlogGenerationHealthRepository;
use App\Repositories\Blog\OpenRouterKeyRepository;
use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;

/**
 * L'etat de la cle OpenRouter montre a la cliente (decision de Micha du 23/11) : la cle enregistree, et ce que la
 * verification du matin en a dit si elle a eu lieu depuis l'enregistrement (refusee, ou une date d'expiration
 * changee chez OpenRouter). Sinon, la date lue a l'enregistrement.
 *
 * Plan de requetes : les reglages en lisent les deux lignes en une requete (current) ; le tableau de bord, qui a
 * deja la verification, n'en lit qu'une (withHealth).
 */
final readonly class OpenRouterKeyStateService
{
    public function __construct(
        private SettingRepository $settings,
        private OpenRouterKeyRepository $keys,
        private int $warningDays,
    ) {}

    public function current(): OpenRouterKeyState
    {
        $stored = $this->settings->many([OpenRouterKeyRepository::KEY, BlogGenerationHealthRepository::KEY]);

        return $this->state(
            $this->keys->decode($stored[OpenRouterKeyRepository::KEY]),
            BlogGenerationHealth::fromStored($stored[BlogGenerationHealthRepository::KEY]),
        );
    }

    public function withHealth(?BlogGenerationHealth $health): OpenRouterKeyState
    {
        return $this->state($this->keys->current(), $health);
    }

    private function state(?OpenRouterKey $key, ?BlogGenerationHealth $health): OpenRouterKeyState
    {
        if ($key === null) {
            return new OpenRouterKeyState(
                null, false, true,
                __('Aucune clé enregistrée. La génération ne peut appeler aucun modèle.'),
                __('Clé OpenRouter absente'),
            );
        }

        $checked = $health !== null && $health->checkedAt->greaterThanOrEqualTo($key->savedAt) ? $health->key : null;

        if ($checked === OpenRouterKeyCheck::Rejected) {
            return new OpenRouterKeyState(
                $key->masked(), false, true,
                __('Refusée par OpenRouter. Collez une nouvelle clé.'),
                __('Clé OpenRouter refusée'),
            );
        }

        $expiresAt = $checked === OpenRouterKeyCheck::Accepted ? $health?->keyExpiresAt : $key->expiresAt;
        $now = CarbonImmutable::now();

        if ($expiresAt === null) {
            return new OpenRouterKeyState($key->masked(), true, false, __('Valide, sans date d\'expiration.'), __('Clé OpenRouter en place'));
        }

        $date = $expiresAt->format('d/m/Y');

        if ($expiresAt->lessThanOrEqualTo($now)) {
            return new OpenRouterKeyState(
                $key->masked(), false, true,
                __('Expirée depuis le :date. Collez une nouvelle clé.', ['date' => $date]),
                __('Clé OpenRouter expirée'),
            );
        }

        if ($expiresAt->lessThanOrEqualTo($now->addDays($this->warningDays))) {
            return new OpenRouterKeyState(
                $key->masked(), true, true,
                __('Expire bientôt, le :date. Collez une nouvelle clé avant cette date.', ['date' => $date]),
                __('Clé OpenRouter valable jusqu\'au :date', ['date' => $date]),
            );
        }

        return new OpenRouterKeyState($key->masked(), true, false, __('Valide. Expire le :date.', ['date' => $date]), __('Clé OpenRouter en place'));
    }
}
