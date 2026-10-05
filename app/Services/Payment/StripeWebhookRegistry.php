<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Data\Payment\StripeWebhookSyncData;
use App\Exceptions\Payment\PaymentException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The Stripe webhook endpoint calling our URL, and the events it listens to. The site handles payment,
 * expiry, refund and lost-dispute events (StripePaymentGateway::parseWebhook) ; the endpoint must listen to
 * all of them, or a refund made in the Stripe dashboard never reaches the site.
 *
 * ensureEvents() (deploy command) ADDS the missing events to the existing endpoint: it never removes an event
 * and never creates an endpoint (Stripe only reveals the signing secret at creation, and that secret lives in
 * .env). Updating the events keeps the endpoint's signing secret.
 */
final readonly class StripeWebhookRegistry
{
    /** Events the site handles (cf. StripePaymentGateway::parseWebhook). */
    public const REQUIRED_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'charge.refunded',
        'charge.dispute.closed',
    ];

    public function ensureEvents(string $url, bool $dry = false): StripeWebhookSyncData
    {
        try {
            $endpoints = (array) $this->api()->get('/webhook_endpoints', ['limit' => 100])->throw()->json('data', []);
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('list webhook endpoints', $e);
        }

        $endpoint = collect($endpoints)->first(fn (array $candidate): bool => ($candidate['url'] ?? null) === $url);
        if ($endpoint === null) {
            return new StripeWebhookSyncData(endpointId: null, listensToAll: false, addedEvents: []);
        }

        $id = (string) $endpoint['id'];
        $current = array_values((array) Arr::get($endpoint, 'enabled_events', []));

        if (in_array('*', $current, true)) {
            return new StripeWebhookSyncData(endpointId: $id, listensToAll: true, addedEvents: []);
        }

        $missing = array_values(array_diff(self::REQUIRED_EVENTS, $current));

        if ($missing !== [] && ! $dry) {
            try {
                $this->api()->asForm()->post("/webhook_endpoints/{$id}", [
                    'enabled_events' => array_values(array_unique([...$current, ...$missing])),
                ])->throw();
            } catch (Throwable $e) {
                throw PaymentException::apiRequestFailed('update webhook endpoint events', $e);
            }
        }

        return new StripeWebhookSyncData(endpointId: $id, listensToAll: false, addedEvents: $missing);
    }

    private function api(): PendingRequest
    {
        return Http::withToken((string) config('payment.drivers.stripe.secret_key'))
            ->baseUrl('https://api.stripe.com/v1')
            ->timeout(15)
            ->connectTimeout(5);
    }
}
