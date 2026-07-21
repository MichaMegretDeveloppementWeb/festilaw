<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Webhook;

use App\Actions\Web\Payment\MarkPaymentExpiredAction;
use App\Actions\Web\Payment\MarkPaymentFailedAction;
use App\Actions\Web\Payment\MarkPaymentProcessingAction;
use App\Actions\Web\Payment\MarkPaymentRefundedAction;
use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Data\Payment\PaymentWebhookData;
use App\Enums\Payment\PaymentEventOutcome;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives a payment provider webhook (Stripe...), verifies + parses it via the matching gateway,
 * and confirms (or fails) the payment synchronously. Idempotent (the Actions are), no worker/cron.
 */
final class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentGatewayRegistry $gateways,
        MarkPaymentSucceededAction $markPaymentSucceeded,
        MarkPaymentFailedAction $markPaymentFailed,
        MarkPaymentProcessingAction $markPaymentProcessing,
        MarkPaymentExpiredAction $markPaymentExpired,
        MarkPaymentRefundedAction $markPaymentRefunded,
    ): Response {
        try {
            $event = $gateways->get($provider)->parseWebhook($request);
        } catch (BaseAppException $e) {
            Log::channel('payments')->warning($e->getMessage(), ['exception' => $e, 'provider' => $provider]);

            return response()->noContent(400);
        }

        try {
            $payment = $this->matchPayment($provider, $event);

            if ($payment === null) {
                // Non rapproche : si le provider annonce un paiement, notre ligne n'existe peut-etre pas
                // encore (course creation/webhook) → 500 pour que le provider reessaie plus tard. Les
                // autres issues non rapprochees sont sans effet (rien a mettre a jour).
                if ($event->outcome === PaymentEventOutcome::Paid) {
                    Log::channel('payments')->warning('Payment webhook (paid) not matched to any payment; requesting retry.', [
                        'provider' => $provider,
                        'provider_reference' => $event->providerReference,
                        'client_reference' => $event->clientReference,
                    ]);

                    return response()->noContent(500);
                }

                // Un remboursement/litige non rapproche ne peut pas etre rejoue utilement (l'objet est une
                // Charge) : on trace pour traitement manuel du support plutot que de le perdre en silence.
                if ($event->outcome === PaymentEventOutcome::Refunded) {
                    Log::channel('payments')->warning('Refund/chargeback webhook not matched to any payment; manual review needed.', [
                        'provider' => $provider,
                        'provider_reference' => $event->providerReference,
                        'client_reference' => $event->clientReference,
                    ]);
                }

                return response()->noContent();
            }

            match ($event->outcome) {
                PaymentEventOutcome::Paid => $markPaymentSucceeded->execute($payment, $event->providerReference),
                PaymentEventOutcome::Failed => $markPaymentFailed->execute($payment),
                PaymentEventOutcome::Processing => $markPaymentProcessing->execute($payment),
                PaymentEventOutcome::Expired => $markPaymentExpired->execute($payment),
                PaymentEventOutcome::Refunded => $markPaymentRefunded->execute($payment),
                PaymentEventOutcome::Unresolved => null,
            };
        } catch (Throwable $e) {
            // Erreur inattendue cote traitement : on trace et on repond 500 pour que le provider reessaie.
            Log::channel('payments')->error('Payment webhook processing failed.', ['exception' => $e, 'provider' => $provider]);

            return response()->noContent(500);
        }

        return response()->noContent();
    }

    /**
     * Reconciles the event to a Payment by provider reference, falling back to our own id carried in
     * the event (clientReference) · covers the rare case where the provider reference was never stored.
     */
    private function matchPayment(string $provider, PaymentWebhookData $event): ?Payment
    {
        $hasReference = $event->providerReference !== '';
        $hasClientReference = $event->clientReference !== null && $event->clientReference !== '';

        // Sans aucun identifiant on ne rapproche rien (une clause vide matcherait tout).
        if (! $hasReference && ! $hasClientReference) {
            return null;
        }

        return Payment::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($event, $hasReference, $hasClientReference): void {
                if ($hasReference) {
                    $query->orWhere('provider_reference', $event->providerReference);
                }
                if ($hasClientReference) {
                    $query->orWhere('id', $event->clientReference);
                }
            })
            ->first();
    }
}
