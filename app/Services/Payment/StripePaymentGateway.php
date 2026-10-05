<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Data\Payment\CheckoutSessionData;
use App\Data\Payment\PaymentWebhookData;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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
    public function __construct(
        private readonly array $config,
        private readonly PaymentReturnService $returns,
    ) {}

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
        // URLs de retour signees, independantes du token du dossier (qui peut changer pendant le paiement).
        [$successUrl, $cancelUrl] = $this->returns->returnUrls($payment);

        try {
            // Idempotency-Key stable par ligne Payment : si Stripe cree la session mais que la reponse se
            // perd (timeout) et que le client HTTP rejoue le POST (->retry), Stripe renvoie la MEME session
            // au lieu d'en creer une seconde (anti double-debit).
            $session = $this->api()->withHeaders(['Idempotency-Key' => 'checkout-'.$payment->id])->asForm()->post('/checkout/sessions', [
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
                ...$this->submitNote($payment),
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
     * Mot affiche sous le bouton de paiement Stripe. Audit SCALE seulement : la consultation se reserve juste
     * apres le paiement, dans l'espace Scale (demande de Festilaw du 04/10/2026). Rien pour les abonnements.
     *
     * @return array{custom_text?: array{submit: array{message: string}}}
     */
    private function submitNote(Payment $payment): array
    {
        if ($payment->type !== PaymentType::ScaleAudit) {
            return [];
        }

        return ['custom_text' => ['submit' => ['message' => __('After payment, you\'ll return to your Scale space to book your consultation.')]]];
    }

    /**
     * Libelle de la ligne sur la page Stripe : pack + annee de service (ex. "Festilaw Pro Pack 2026"), ou le
     * passage au Pro (ex. "Festilaw Pro Pack upgrade 2026") : le dossier est encore Creator a ce moment-la.
     */
    private function lineItemName(Payment $payment): string
    {
        $year = $payment->service_year;

        if ($payment->type === PaymentType::PackUpgrade) {
            return 'Festilaw '.SubmissionType::Pro->label().' upgrade'.($year ? ' '.$year : '');
        }

        $pack = $payment->submission?->type->label() ?? 'Festilaw';

        return 'Festilaw '.$pack.($year ? ' '.$year : '');
    }

    /**
     * Rembourse integralement le paiement (annulation d'un passage au Pro par Festilaw, SC12) : retrouve le
     * PaymentIntent de la session Checkout puis cree le remboursement. Idempotency-Key stable par paiement :
     * un nouvel essai ne rembourse jamais deux fois. Le webhook charge.refunded confirmera ensuite.
     */
    public function refund(Payment $payment): void
    {
        $this->assertConfigured('secret_key');

        $sessionId = (string) ($payment->provider_reference ?? '');

        try {
            if ($sessionId === '') {
                throw new RuntimeException('No checkout session to refund.');
            }

            $session = $this->api()->get("/checkout/sessions/{$sessionId}")->throw()->json();
            $paymentIntent = (string) Arr::get($session, 'payment_intent', '');
            if ($paymentIntent === '') {
                throw new RuntimeException('The checkout session has no payment intent.');
            }

            $this->api()->withHeaders(['Idempotency-Key' => 'refund-'.$payment->id])->asForm()->post('/refunds', [
                'payment_intent' => $paymentIntent,
                'metadata' => ['payment_id' => (string) $payment->id],
            ])->throw();
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('refund payment', $e);
        }
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
            amountCents: $this->amountTotal($session),
        );
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        $this->assertConfigured('webhook_secret');

        $event = $this->verifiedEvent($request);
        $type = (string) Arr::get($event, 'type', '');
        $object = (array) Arr::get($event, 'data.object', []);

        // Remboursement reel : l'objet est une Charge, pas une session · on rapproche par notre payment_id
        // propage dans les metadata. Stripe emet charge.refunded pour un remboursement PARTIEL comme TOTAL ;
        // on ne desactive le dossier que sur un remboursement INTEGRAL. Un remboursement partiel (geste
        // commercial, ajustement) ne coupe pas la couverture -> Unresolved (aucun effet), traite a la main.
        if ($type === 'charge.refunded') {
            $amount = (int) Arr::get($object, 'amount', 0);
            $refunded = (int) Arr::get($object, 'amount_refunded', 0);
            $fullyRefunded = (bool) Arr::get($object, 'refunded', false) || ($amount > 0 && $refunded >= $amount);

            return new PaymentWebhookData(
                providerReference: (string) Arr::get($object, 'id', ''),
                outcome: $fullyRefunded ? PaymentEventOutcome::Refunded : PaymentEventOutcome::Unresolved,
                clientReference: $this->paymentIdFrom($object, lookUp: $fullyRefunded),
            );
        }

        // Litige (chargeback) : ce N'EST PAS un remboursement. A l'OUVERTURE les fonds sont seulement
        // retenus et le litige peut etre gagne : on ne coupe donc rien (Unresolved). On ne desactive le
        // dossier que si le litige est PERDU (fonds definitivement repris). Un litige gagne ne desactive
        // jamais la couverture · rien a "reactiver" puisqu'on n'a rien coupe.
        if ($type === 'charge.dispute.created' || $type === 'charge.dispute.closed') {
            $lost = $type === 'charge.dispute.closed' && (string) Arr::get($object, 'status', '') === 'lost';

            return new PaymentWebhookData(
                providerReference: (string) Arr::get($object, 'id', ''),
                outcome: $lost ? PaymentEventOutcome::Refunded : PaymentEventOutcome::Unresolved,
                clientReference: $this->paymentIdFrom($object, lookUp: $lost),
            );
        }

        return new PaymentWebhookData(
            providerReference: (string) Arr::get($object, 'id', ''),
            outcome: $this->webhookOutcome($type, (string) Arr::get($object, 'payment_status')),
            // Notre payment id (envoye en client_reference_id) : rapprochement de secours.
            clientReference: ((string) Arr::get($object, 'client_reference_id', '')) ?: null,
            amountCents: $this->amountTotal($object),
        );
    }

    /**
     * Montant reellement encaisse d'une session Checkout (amount_total, en centimes), compare au montant
     * attendu a la confirmation (ReviewConfirmedPaymentAction). Null s'il est absent.
     *
     * @param  array<string, mixed>  $session
     */
    private function amountTotal(array $session): ?int
    {
        $amount = Arr::get($session, 'amount_total');

        return is_numeric($amount) ? (int) $amount : null;
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

    /**
     * Notre payment id pour un remboursement ou un litige. Une Charge le porte (metadata heritees du
     * PaymentIntent, posees au Checkout) ; un Dispute jamais (metadata vides, constate sur de vrais evenements
     * Stripe) : on le lit alors sur son PaymentIntent, ou a defaut sur sa Charge. Appel a Stripe seulement
     * quand l'issue modifie un paiement ($lookUp) ; s'il echoue, exception : le webhook repond 400 et Stripe
     * renverra l'evenement.
     *
     * @param  array<string, mixed>  $object
     */
    private function paymentIdFrom(array $object, bool $lookUp): ?string
    {
        $paymentId = (string) Arr::get($object, 'metadata.payment_id', '');
        if ($paymentId !== '' || ! $lookUp) {
            return $paymentId !== '' ? $paymentId : null;
        }

        $paymentIntent = (string) Arr::get($object, 'payment_intent', '');
        $charge = (string) Arr::get($object, 'charge', '');
        $path = match (true) {
            $paymentIntent !== '' => "/payment_intents/{$paymentIntent}",
            $charge !== '' => "/charges/{$charge}",
            default => null,
        };
        if ($path === null) {
            return null;
        }

        $this->assertConfigured('secret_key');

        try {
            $paymentId = (string) $this->api()->get($path)->throw()->json('metadata.payment_id', '');
        } catch (Throwable $e) {
            throw PaymentException::apiRequestFailed('retrieve disputed payment', $e);
        }

        return $paymentId !== '' ? $paymentId : null;
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
