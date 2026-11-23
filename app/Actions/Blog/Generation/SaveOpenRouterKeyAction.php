<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\OpenRouterKey;
use App\Exceptions\Blog\BlogHealthCheckException;
use App\Exceptions\Blog\OpenRouterKeyException;
use App\Repositories\Blog\OpenRouterKeyRepository;
use App\Services\Blog\Generation\Health\OpenRouterCatalogReader;
use Carbon\CarbonImmutable;

/**
 * Enregistre la cle OpenRouter que la cliente colle dans les reglages (decision de Micha du 23/11). Elle est
 * d'abord verifiee par l'adresse gratuite d'OpenRouter, sans appeler de modele, et sa date d'expiration est lue
 * au passage. Refusee ou inverifiable, elle n'est pas enregistree et l'ancienne reste en place. Une seule
 * ecriture.
 */
final readonly class SaveOpenRouterKeyAction
{
    public function __construct(
        private OpenRouterCatalogReader $openRouter,
        private OpenRouterKeyRepository $keys,
    ) {}

    /** @throws OpenRouterKeyException */
    public function execute(#[\SensitiveParameter] string $secret): OpenRouterKey
    {
        try {
            $status = $this->openRouter->withKey($secret)->keyStatus();
        } catch (BlogHealthCheckException $e) {
            throw $e->rejectsTheKey() ? OpenRouterKeyException::rejected($e) : OpenRouterKeyException::unverifiable($e);
        }

        $savedAt = CarbonImmutable::now();
        $this->keys->store($secret, $status->expiresAt, $savedAt);

        return new OpenRouterKey($secret, $status->expiresAt, $savedAt);
    }
}
