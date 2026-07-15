<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Exceptions\Signature\SignatureException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns the long-lived Zoho refresh token into a short-lived access token (~1 h), cached so we do
 * not re-mint one per API call. Server-to-server: no interactive consent at runtime.
 */
final readonly class ZohoTokenProvider
{
    private const CACHE_KEY = 'zoho_sign.access_token';

    /** @param  array<string, mixed>  $config */
    public function __construct(private array $config) {}

    public function accessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()->post(
                rtrim((string) $this->config['accounts_url'], '/').'/oauth/v2/token',
                [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config['client_id'] ?? null,
                    'client_secret' => $this->config['client_secret'] ?? null,
                    'refresh_token' => $this->config['refresh_token'] ?? null,
                ],
            )->throw();
        } catch (Throwable $e) {
            throw SignatureException::tokenExchangeFailed($e);
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            // Zoho renvoie parfois un 200 porteur d'un champ "error" (ex: invalid_client).
            throw SignatureException::tokenExchangeFailed();
        }

        $ttl = (int) $response->json('expires_in', 3600);
        Cache::put(self::CACHE_KEY, $token, max(60, $ttl - 120));

        return $token;
    }
}
