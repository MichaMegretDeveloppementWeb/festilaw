<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use RuntimeException;
use Tests\TestCase;

/**
 * Un vrai aller-retour Livewire dans un test : l'etat du composant est lu dans le HTML d'une page, puis envoye
 * au point d'entree de Livewire, comme le fait le navigateur. `Livewire::test()` appelle le composant
 * directement ; il ne rejoue pas la route de la page d'origine (ses controles, la liaison de ses parametres),
 * que Livewire rejoue pourtant a chaque vraie requete. Cette aide sert a prouver ce passage-la.
 */
final class LivewireRoundTrip
{
    /**
     * Appelle une methode du premier composant de la page, apres avoir applique des changements de proprietes.
     *
     * @param  array<string, mixed>  $updates
     * @param  list<mixed>  $params
     */
    public static function call(TestCase $test, string $pageHtml, string $method, array $updates = [], array $params = []): TestResponse
    {
        return $test->postJson(app(HandleRequests::class)->getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => self::snapshot($pageHtml),
                'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
            ]],
        ], ['X-Livewire' => '1']);
    }

    /** L'etat du premier composant de la page, tel que le navigateur le renverrait. */
    public static function snapshot(string $pageHtml): string
    {
        if (preg_match('/wire:snapshot="([^"]+)"/', $pageHtml, $found) !== 1) {
            throw new RuntimeException('No Livewire component was found in this page.');
        }

        return html_entity_decode($found[1], ENT_QUOTES);
    }

    /**
     * L'etat du composant rendu par le serveur dans la reponse d'un aller-retour.
     *
     * @return array<string, mixed>
     */
    public static function data(TestResponse $response): array
    {
        return json_decode((string) $response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR)['data'];
    }
}
