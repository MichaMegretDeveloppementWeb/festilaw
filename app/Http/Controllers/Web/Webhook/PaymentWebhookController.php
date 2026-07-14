<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Webhook;

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives a payment provider webhook (Stripe...), verifies + parses it via the matching gateway,
 * and confirms the payment synchronously. Idempotent (the Action is), no worker/cron.
 */
final class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentGatewayRegistry $gateways,
        MarkPaymentSucceededAction $markPaymentSucceeded,
    ): Response {
        try {
            $event = $gateways->get($provider)->parseWebhook($request);
        } catch (BaseAppException $e) {
            // TODO: canal de log dedie 'payments' (audit) une fois configure.
            Log::warning($e->getMessage(), ['exception' => $e, 'provider' => $provider]);

            return response()->noContent(400);
        }

        $payment = Payment::query()
            ->where('provider', $provider)
            ->where('provider_reference', $event->providerReference)
            ->first();

        if ($payment !== null && $event->paid) {
            $markPaymentSucceeded->execute($payment, $event->providerReference);
        }

        return response()->noContent();
    }
}
