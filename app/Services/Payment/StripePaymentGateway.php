<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookData;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Stripe Checkout adapter (one-time payment). No card ever touches our servers · Stripe hosts the
 * payment page. We create a Checkout Session (amount from the Payment), redirect the buyer to it, and
 * confirm either by polling on return (checkStatus) or by the signed webhook (parseWebhook · source of
 * truth). Our Payment id travels in the session metadata for back-office reconciliation. Talks to the
 * Stripe REST API over HTTP (no SDK dependency); every technical error becomes a typed PaymentException.
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    /** Stripe-Signature tolerance against replay, in seconds. */
    private const WEBHOOK_TOLERANCE = 300;

    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Card';
    }

    public function createCheckout(Payment $payment): CheckoutSessionData
    {
        $this->assertConfigured('secret_key');

        $submission = $payment->submission;
        $journeyUrl = route('get-started.starter.journey', [
            'locale' => $submission->locale ?: config('app.locale'),
            'dossier' => $submission->resume_token,
        ]);

        try {
            $session = $this->api()->asForm()->post('/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $payment->id,
                'customer_email' => (string) $submission->email,
                'success_url' => $journeyUrl.'?payment_return=1',
                'cancel_url' => $journeyUrl.'?payment_cancelled=1',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $payment->currency),
                        'unit_amount' => $payment->amount_cents,
                        'product_data' => ['name' => 'Festilaw Creator Pack (12 months)'],
                    ],
                ]],
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'submission_reference' => (string) $submission->reference,
                ],
            ])->throw()->json();
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('create checkout session', $e);
        }

        $id = (string) Arr::get($session, 'id', '');
        $url = (string) Arr::get($session, 'url', '');
        if ($id === '' || $url === '') {
            throw PaymentException::apiRequestFailed('create checkout session');
        }

        return new CheckoutSessionData(providerReference: $id, redirectUrl: $url);
    }

    public function checkStatus(Payment $payment): PaymentWebhookData
    {
        $this->assertConfigured('secret_key');

        $sessionId = (string) ($payment->provider_reference ?? '');
        if ($sessionId === '') {
            return new PaymentWebhookData('', false);
        }

        try {
            $session = $this->api()->get("/checkout/sessions/{$sessionId}")->throw()->json();
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('retrieve checkout session', $e);
        }

        return new PaymentWebhookData(
            providerReference: $sessionId,
            paid: Arr::get($session, 'payment_status') === 'paid',
        );
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        $this->assertConfigured('webhook_secret');

        $event = $this->verifiedEvent($request);
        $type = (string) Arr::get($event, 'type', '');
        $session = (array) Arr::get($event, 'data.object', []);

        // On ne confirme que sur une session de checkout effectivement payee.
        $paid = in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && Arr::get($session, 'payment_status') === 'paid';

        return new PaymentWebhookData(
            providerReference: (string) Arr::get($session, 'id', ''),
            paid: $paid,
        );
    }

    /**
     * Verifies the Stripe-Signature header (scheme v1: HMAC-SHA256 of "{timestamp}.{payload}") and
     * returns the decoded event. Throws PaymentException on any mismatch or stale timestamp.
     *
     * @return array<string, mixed>
     */
    private function verifiedEvent(Request $request): array
    {
        $payload = $request->getContent();
        $secret = (string) $this->config['webhook_secret'];

        // En-tete "t=timestamp,v1=signature[,v1=...]".
        $parts = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature', '')) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[$k][] = $v;
        }

        $timestamp = $parts['t'][0] ?? '';
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || $signatures === []) {
            throw PaymentException::webhookSignatureInvalid();
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $matches = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                $matches = true;
                break;
            }
        }

        if (! $matches || abs(now()->timestamp - (int) $timestamp) > self::WEBHOOK_TOLERANCE) {
            throw PaymentException::webhookSignatureInvalid();
        }

        return (array) json_decode($payload, true);
    }

    private function api(): PendingRequest
    {
        return Http::withToken((string) $this->config['secret_key'])
            ->baseUrl('https://api.stripe.com/v1');
    }

    private function assertConfigured(string $key): void
    {
        if (empty($this->config[$key])) {
            throw PaymentException::providerNotConfigured('stripe');
        }
    }
}
