<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Webhook;

use App\Actions\Web\Payment\MarkPaymentFailedAction;
use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Data\Payment\PaymentWebhookData;
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
    ): Response {
        try {
            $event = $gateways->get($provider)->parseWebhook($request);
        } catch (BaseAppException $e) {
            // TODO: canal de log dedie 'payments' (audit) une fois configure.
            Log::warning($e->getMessage(), ['exception' => $e, 'provider' => $provider]);

            return response()->noContent(400);
        }

        try {
            $payment = $this->matchPayment($provider, $event);

            if ($payment !== null && $event->paid) {
                $markPaymentSucceeded->execute($payment, $event->providerReference);
            } elseif ($payment !== null && $event->failed) {
                $markPaymentFailed->execute($payment);
            }
        } catch (Throwable $e) {
            // Erreur inattendue cote traitement : on trace et on repond 500 pour que le provider reessaie.
            Log::error('Payment webhook processing failed.', ['exception' => $e, 'provider' => $provider]);

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
