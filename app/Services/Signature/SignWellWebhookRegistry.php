<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Data\Signature\WebhookSyncData;
use App\Exceptions\Signature\SignatureException;
use App\Repositories\SettingRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The SignWell webhook(s) pointing at our callback URL, and their ids. SignWell signs every webhook event
 * with HMAC-SHA256 over "{event.type}@{event.time}" keyed by the WEBHOOK ID (not the API key), so the ids
 * are what the gateway needs to accept an event. They are discovered from the SignWell API and stored in
 * the settings table: nobody copies a key by hand.
 *
 * sync() (deploy command) finds the webhooks registered for our URL, creates one only if there is none,
 * and stores their ids. It never deletes anything (the webhook created in the SignWell interface is kept).
 * refreshKnownIds() re-reads the list without creating anything, at most once per REFRESH_COOLDOWN
 * seconds: self-healing if the webhook is recreated in the SignWell interface.
 */
final readonly class SignWellWebhookRegistry
{
    private const SETTING_KEY = 'signwell.webhook_ids';

    private const REFRESH_LOCK = 'signwell:webhook-ids-refresh';

    private const REFRESH_COOLDOWN = 600;

    /** @param  array<string, mixed>  $config */
    public function __construct(
        private array $config,
        private SettingRepository $settings,
    ) {}

    /** @return list<string> */
    public function knownIds(): array
    {
        $ids = json_decode((string) $this->settings->get(self::SETTING_KEY), true);

        return is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    /**
     * Finds (or creates, if none) the webhook(s) calling $callbackUrl and stores their ids. Dry run:
     * reports what exists, creates and stores nothing.
     */
    public function sync(string $callbackUrl, bool $dry = false): WebhookSyncData
    {
        $ids = $this->idsFor($callbackUrl);
        $created = false;

        if ($ids === [] && ! $dry) {
            $ids = [$this->create($callbackUrl)];
            $created = true;
        }

        if (! $dry) {
            $this->store($ids);
        }

        return new WebhookSyncData($callbackUrl, $ids, $created);
    }

    /**
     * Re-reads the webhooks calling $callbackUrl (never creates one) and stores their ids, at most once
     * per cooldown. Best effort: on a provider error the known ids are returned unchanged.
     *
     * @return list<string>
     */
    public function refreshKnownIds(string $callbackUrl): array
    {
        if (! Cache::add(self::REFRESH_LOCK, true, self::REFRESH_COOLDOWN)) {
            return $this->knownIds();
        }

        try {
            $ids = $this->idsFor($callbackUrl);
        } catch (Throwable $e) {
            Log::channel('signature')->warning('SignWell webhook ids refresh failed.', ['exception' => $e]);

            return $this->knownIds();
        }

        if ($ids !== []) {
            $this->store($ids);
        }

        return $ids !== [] ? $ids : $this->knownIds();
    }

    /** @return list<string> ids of the SignWell webhooks whose callback is exactly $callbackUrl */
    private function idsFor(string $callbackUrl): array
    {
        $this->assertConfigured();

        try {
            $hooks = $this->api()->get('/hooks')->throw()->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('list webhooks', $e);
        }

        $target = rtrim($callbackUrl, '/');
        $ids = [];
        foreach (is_array($hooks) ? $hooks : [] as $hook) {
            if (is_array($hook) && rtrim((string) ($hook['callback_url'] ?? ''), '/') === $target && (string) ($hook['id'] ?? '') !== '') {
                $ids[] = (string) $hook['id'];
            }
        }

        return $ids;
    }

    private function create(string $callbackUrl): string
    {
        $payload = ['callback_url' => $callbackUrl];
        if (! empty($this->config['api_application_id'])) {
            $payload['api_application_id'] = (string) $this->config['api_application_id'];
        }

        try {
            $hook = $this->api()->post('/hooks', $payload)->throw()->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('create webhook', $e);
        }

        $id = (string) ($hook['id'] ?? '');
        if ($id === '') {
            throw SignatureException::apiRequestFailed('create webhook');
        }

        return $id;
    }

    /** @param  list<string>  $ids */
    private function store(array $ids): void
    {
        $this->settings->put(self::SETTING_KEY, (string) json_encode(array_values(array_unique($ids))));
    }

    private function assertConfigured(): void
    {
        if (empty($this->config['api_key'])) {
            throw SignatureException::providerNotConfigured('signwell');
        }
    }

    private function api(): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => (string) $this->config['api_key']])
            ->acceptJson()
            ->baseUrl(rtrim((string) ($this->config['api_base_url'] ?? 'https://www.signwell.com/api/v1'), '/'))
            ->timeout(15)
            ->connectTimeout(5);
    }
}
