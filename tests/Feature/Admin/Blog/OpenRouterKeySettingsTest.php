<?php

declare(strict_types=1);

use App\Data\Blog\Generation\Health\BlogGenerationHealth;
use App\Enums\Blog\Generation\OpenRouterKeyCheck;
use App\Livewire\Admin\Blog\OpenRouterKeyForm;
use App\Models\Setting;
use App\Repositories\Blog\BlogGenerationHealthRepository;
use App\Repositories\Blog\OpenRouterKeyRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Support\BlogAi;

/*
 | La cle OpenRouter dans les reglages de la generation (decision de Micha du 23/11) : la cliente la colle, le site
 | la verifie chez OpenRouter, la garde chiffree, et n'en montre ensuite que le debut, la fin et l'etat.
 */

uses(RefreshDatabase::class);

const NEW_OPENROUTER_KEY = 'sk-or-v1-abcd0123456789abcdef0123456789abcdef0123456789abcdef0123wxyz';

beforeEach(function () {
    $this->travelTo('2026-11-23 10:00:00');
});

/** @param  array<string, mixed>  $data */
function openRouterKeyAnswer(array $data = []): void
{
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => [...['limit' => null, 'limit_remaining' => null, 'expires_at' => null], ...$data]])]);
}

it('saves a key that OpenRouter accepts, encrypted, then shows only its start and end with its expiry date', function () {
    openRouterKeyAnswer(['expires_at' => '2027-12-31T00:00:00Z']);

    Livewire::test(OpenRouterKeyForm::class)
        ->assertSee('Aucune clé enregistrée. La génération ne peut appeler aucun modèle.')
        ->set('newKey', '  '.NEW_OPENROUTER_KEY.' ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('newKey', '')
        ->assertDispatched('ui-toast', type: 'success', title: 'Clé vérifiée et enregistrée.')
        ->assertSee('sk-or-v1-abcd…wxyz')
        ->assertSee('Valide. Expire le 31/12/2027.')
        ->assertDontSee('0123456789abcdef');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.NEW_OPENROUTER_KEY));
    expect(Setting::query()->where('key', OpenRouterKeyRepository::KEY)->value('value'))->not->toContain('0123456789abcdef')
        ->and(app(OpenRouterKeyRepository::class)->current()?->secret)->toBe(NEW_OPENROUTER_KEY);
});

it('refuses a key that OpenRouter rejects, says so under the field, and keeps the key in place', function () {
    BlogAi::saveOpenRouterKey('sk-or-v1-old-key-kept-in-place-000000000000');
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['error' => ['message' => 'User not found.']], 401)]);
    Log::spy();

    Livewire::test(OpenRouterKeyForm::class)
        ->set('newKey', NEW_OPENROUTER_KEY)
        ->call('save')
        ->assertHasErrors(['newKey'])
        ->assertSee('OpenRouter refuse cette clé. Vérifiez que vous l\'avez copiée en entier.')
        ->assertSee('sk-or-v1-old-…0000');

    expect(app(OpenRouterKeyRepository::class)->current()?->secret)->toBe('sk-or-v1-old-key-kept-in-place-000000000000');
    // Un refus d'utilisation reste hors du rapport du matin (decision du 23/11).
    Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message): bool => $level === 'warning'
        && $message === 'OpenRouter rejected the key pasted in the generation settings.');
});

it('replaces nothing when OpenRouter does not answer, and reports it as an error', function () {
    BlogAi::saveOpenRouterKey('sk-or-v1-old-key-kept-in-place-000000000000');
    Http::fake(['openrouter.ai/api/v1/key' => Http::response('', 503)]);
    Log::spy();

    Livewire::test(OpenRouterKeyForm::class)
        ->set('newKey', NEW_OPENROUTER_KEY)
        ->call('save')
        ->assertHasErrors(['newKey'])
        ->assertSee('OpenRouter ne répond pas. La clé n\'a pas été changée.');

    expect(app(OpenRouterKeyRepository::class)->current()?->secret)->toBe('sk-or-v1-old-key-kept-in-place-000000000000');
    Log::shouldHaveReceived('log')->withArgs(fn (string $level): bool => $level === 'error');
});

it('asks for the key before calling OpenRouter', function () {
    Http::fake();

    Livewire::test(OpenRouterKeyForm::class)
        ->set('newKey', '   ')
        ->call('save')
        ->assertHasErrors(['newKey'])
        ->assertSee('Collez la nouvelle clé.');

    Http::assertNothingSent();
});

it('says what the morning check found of the key since it was saved', function (?OpenRouterKeyCheck $check, ?string $expiresAt, string $sentence) {
    BlogAi::saveOpenRouterKey(NEW_OPENROUTER_KEY, expiresAt: CarbonImmutable::parse('2028-06-30'), savedAt: CarbonImmutable::parse('2026-11-20 09:00'));
    app(BlogGenerationHealthRepository::class)->store(new BlogGenerationHealth(
        CarbonImmutable::parse('2026-11-23 05:45'), [], [], $check, $expiresAt === null ? null : CarbonImmutable::parse($expiresAt),
    ));

    Livewire::test(OpenRouterKeyForm::class)->assertSee($sentence);
})->with([
    'rejected since' => [OpenRouterKeyCheck::Rejected, null, 'Refusée par OpenRouter. Collez une nouvelle clé.'],
    'expiring within 14 days' => [OpenRouterKeyCheck::Accepted, '2026-12-01', 'Expire bientôt, le 01/12/2026. Collez une nouvelle clé avant cette date.'],
    'expired' => [OpenRouterKeyCheck::Accepted, '2026-11-22', 'Expirée depuis le 22/11/2026. Collez une nouvelle clé.'],
    'no expiry any more' => [OpenRouterKeyCheck::Accepted, null, 'Valide, sans date d\'expiration.'],
    'check not conclusive' => [null, null, 'Valide. Expire le 30/06/2028.'],
]);

it('trusts a key saved after the morning check over what that check said', function () {
    app(BlogGenerationHealthRepository::class)->store(new BlogGenerationHealth(CarbonImmutable::parse('2026-11-23 05:45'), [], [], OpenRouterKeyCheck::Rejected));
    BlogAi::saveOpenRouterKey(NEW_OPENROUTER_KEY, savedAt: CarbonImmutable::parse('2026-11-23 09:30'));

    Livewire::test(OpenRouterKeyForm::class)->assertSee('Valide, sans date d\'expiration.');
});

it('counts a key it can no longer decrypt as missing, and says why in the log', function () {
    Setting::query()->create(['key' => OpenRouterKeyRepository::KEY, 'value' => json_encode(['key' => 'not-encrypted', 'saved_at' => '2026-11-20T09:00:00Z'])]);
    Log::spy();

    Livewire::test(OpenRouterKeyForm::class)->assertSee('Aucune clé enregistrée.');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'cannot be decrypted'));
});
