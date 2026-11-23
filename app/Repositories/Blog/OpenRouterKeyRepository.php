<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Data\Blog\Generation\OpenRouterKey;
use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * La cle OpenRouter du site, gardee dans `settings`, chiffree avec la cle de l'application. Une cle illisible
 * (APP_KEY changee) compte comme absente, et le journal le dit en erreur : il faut la coller de nouveau.
 */
final readonly class OpenRouterKeyRepository
{
    public const KEY = 'blog.ai.openrouter_key';

    public function __construct(
        private SettingRepository $settings,
        private Encrypter $encrypter,
    ) {}

    public function current(): ?OpenRouterKey
    {
        return $this->decode($this->settings->get(self::KEY));
    }

    public function store(#[\SensitiveParameter] string $secret, ?CarbonImmutable $expiresAt, CarbonImmutable $savedAt): void
    {
        $this->settings->put(self::KEY, (string) json_encode([
            'key' => $this->encrypter->encryptString($secret),
            'expires_at' => $expiresAt?->toIso8601String(),
            'saved_at' => $savedAt->toIso8601String(),
        ]));
    }

    /** La cle telle que gardee en base (ligne lue avec d'autres reglages, en une requete). */
    public function decode(?string $stored): ?OpenRouterKey
    {
        $data = json_decode((string) $stored, true);

        if (! is_array($data) || ! is_string($data['key'] ?? null) || ! is_string($data['saved_at'] ?? null)) {
            return null;
        }

        try {
            $secret = $this->encrypter->decryptString($data['key']);
        } catch (DecryptException) {
            Log::error('The stored OpenRouter key cannot be decrypted (APP_KEY changed?); it must be pasted again in the generation settings.');

            return null;
        }

        try {
            return new OpenRouterKey(
                $secret,
                is_string($data['expires_at'] ?? null) ? CarbonImmutable::parse($data['expires_at']) : null,
                CarbonImmutable::parse($data['saved_at']),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
