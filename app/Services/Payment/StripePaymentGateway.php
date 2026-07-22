<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookData;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentType;
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
        return __('Card');
    }

    public function createCheckout(Payment $payment): CheckoutSessionData
    {
        $this->assertConfigured('secret_key');

        $submission = $payment->submission;
        [$successUrl, $cancelUrl] = $this->returnUrls($payment);

        try {
            $session = $this->api()->asForm()->post('/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $payment->id,
                'customer_email' => (string) $submission->email,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $payment->currency),
                        'unit_amount' => $payment->amount_cents,
                        'product_data' => ['name' => $this->lineItemName($payment)],
                    ],
                ]],
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'submission_reference' => (string) $submission->reference,
                ],
                // Propage notre id jusqu'au PaymentIntent/Charge : indispensable pour rapprocher un
                // evenement charge.refunded / charge.dispute.created (dont l'objet n'est pas la session).
                'payment_intent_data' => [
                    'metadata' => [
                        'payment_id' => (string) $payment->id,
                    ],
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

    /**
     * URLs de retour [succes, annulation] selon le type de paiement. Un renouvellement part de l'espace
     * dossier (page "mon projet") et doit y revenir pour etre confirme (le dossier est deja "paye", la
     * journey rebondirait sans rien confirmer) ; l'annee 1 revient sur la journey qui poll la confirmation.
     *
     * @return array{0: string, 1: string}
     */
    private function returnUrls(Payment $payment): array
    {
        $token = $payment->submission?->resume_token;

        if ($payment->type === PaymentType::AnnualRenewal) {
            $base = route('my-project', ['dossier' => $token]);

            return [$base.'?renewal_return=1', $base.'?renewal_cancelled=1'];
        }

        $base = route('get-started.starter.journey', ['dossier' => $token]);

        return [$base.'?payment_return=1', $base.'?payment_cancelled=1'];
    }

    /** Libelle de la ligne sur la page Stripe : pack + annee de service (ex. "Festilaw Pro Pack 2026"). */
    private function lineItemName(Payment $payment): string
    {
        $pack = $payment->submission?->type->label() ?? 'Festilaw';
        $year = $payment->service_year;

        return 'Festilaw '.$pack.($year ? ' '.$year : '');
    }

    public function currentCheckoutUrl(Payment $payment): ?string
    {
        $this->assertConfigured('secret_key');

        $sessionId = (string) ($payment->provider_reference ?? '');
        if ($sessionId === '') {
            return null;
        }

        try {
            $session = $this->api()->get("/checkout/sessions/{$sessionId}")->throw()->json();
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('retrieve checkout session', $e);
        }

        // Reutilisable seulement tant que la session est ouverte (ni payee, ni expiree).
        if (Arr::get($session, 'status') !== 'open') {
            return null;
        }

        $url = (string) Arr::get($session, 'url', '');

        return $url !== '' ? $url : null;
    }

    public function checkStatus(Payment $payment): PaymentWebhookData
    {
        $this->assertConfigured('secret_key');

        $sessionId = (string) ($payment->provider_reference ?? '');
        if ($sessionId === '') {
            return new PaymentWebhookData('', PaymentEventOutcome::Unresolved);
        }

        try {
            $session = $this->api()->get("/checkout/sessions/{$sessionId}")->throw()->json();
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('retrieve checkout session', $e);
        }

        return new PaymentWebhookData(
            providerReference: $sessionId,
            outcome: $this->sessionOutcome(
                (string) Arr::get($session, 'status'),
                (string) Arr::get($session, 'payment_status'),
            ),
            clientReference: ((string) Arr::get($session, 'client_reference_id', '')) ?: null,
        );
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        $this->assertConfigured('webhook_secret');

        $event = $this->verifiedEvent($request);
        $type = (string) Arr::get($event, 'type', '');
        $object = (array) Arr::get($event, 'data.object', []);

        // Remboursement reel : l'objet est une Charge, pas une session — on rapproche par notre payment_id
        // propage dans les metadata.
        if ($type === 'charge.refunded') {
            return new PaymentWebhookData(
                providerReference: (string) Arr::get($object, 'id', ''),
                outcome: PaymentEventOutcome::Refunded,
                clientReference: ((string) Arr::get($object, 'metadata.payment_id', '')) ?: null,
            );
        }

        // Litige (chargeback) : ce N'EST PAS un remboursement. A l'OUVERTURE les fonds sont seulement
        // retenus et le litige peut etre gagne : on ne coupe donc rien (Unresolved). On ne desactive le
        // dossier que si le litige est PERDU (fonds definitivement repris). Un litige gagne ne desactive
        // jamais la couverture — rien a "reactiver" puisqu'on n'a rien coupe.
        if ($type === 'charge.dispute.created' || $type === 'charge.dispute.closed') {
            $lost = $type === 'charge.dispute.closed' && (string) Arr::get($object, 'status', '') === 'lost';

            return new PaymentWebhookData(
                providerReference: (string) Arr::get($object, 'id', ''),
                outcome: $lost ? PaymentEventOutcome::Refunded : PaymentEventOutcome::Unresolved,
                clientReference: ((string) Arr::get($object, 'metadata.payment_id', '')) ?: null,
            );
        }

        return new PaymentWebhookData(
            providerReference: (string) Arr::get($object, 'id', ''),
            outcome: $this->webhookOutcome($type, (string) Arr::get($object, 'payment_status')),
            // Notre payment id (envoye en client_reference_id) : rapprochement de secours.
            clientReference: ((string) Arr::get($object, 'client_reference_id', '')) ?: null,
        );
    }

    /**
     * Maps a checkout.session webhook (type + payment_status) onto our normalized outcome. A completed
     * session that is still unpaid is an async method in flight → Processing (confirmed later by
     * async_payment_succeeded / _failed).
     */
    private function webhookOutcome(string $type, string $paymentStatus): PaymentEventOutcome
    {
        return match ($type) {
            'checkout.session.completed' => $paymentStatus === 'paid' ? PaymentEventOutcome::Paid : PaymentEventOutcome::Processing,
            'checkout.session.async_payment_succeeded' => PaymentEventOutcome::Paid,
            'checkout.session.async_payment_failed' => PaymentEventOutcome::Failed,
            'checkout.session.expired' => PaymentEventOutcome::Expired,
            default => PaymentEventOutcome::Unresolved,
        };
    }

    /** Maps a live checkout session (status + payment_status) onto our normalized outcome (for polling). */
    private function sessionOutcome(string $status, string $paymentStatus): PaymentEventOutcome
    {
        return match (true) {
            $paymentStatus === 'paid' => PaymentEventOutcome::Paid,
            $status === 'expired' => PaymentEventOutcome::Expired,
            $status === 'complete' => PaymentEventOutcome::Processing, // complete mais impaye = async en cours
            default => PaymentEventOutcome::Unresolved, // 'open' : le client n'a pas fini
        };
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
            ->baseUrl('https://api.stripe.com/v1')
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 200, throw: false);
    }

    private function assertConfigured(string $key): void
    {
        if (empty($this->config[$key])) {
            throw PaymentException::providerNotConfigured('stripe');
        }
    }
}
