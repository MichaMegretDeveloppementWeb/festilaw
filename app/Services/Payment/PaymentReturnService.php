<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Payment\PaymentType;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;

/**
 * Return trip of a hosted checkout, independent of the dossier's resume token. The token rotates on every
 * emailed link request, so a token baked into the provider's success/cancel URL at checkout creation could
 * be dead by the time the buyer comes back (404). The provider therefore gets a short-lived SIGNED route
 * keyed by the payment, and the return redirects to the dossier's page with its CURRENT token and the
 * existing return flag. Stateless, transaction-agnostic.
 */
final class PaymentReturnService
{
    /** A Stripe Checkout session expires after 24h by default: the return link outlives it comfortably. */
    private const SIGNATURE_TTL_DAYS = 3;

    /**
     * Success and cancel URLs to hand to the payment provider. Relative signature (host and scheme are not
     * part of the hash): robust behind the hosting proxy, the path + query + expiry stay tamper-proof.
     *
     * @return array{0: string, 1: string}
     */
    public function returnUrls(Payment $payment): array
    {
        return [
            $this->signedReturnUrl($payment, 'success'),
            $this->signedReturnUrl($payment, 'cancelled'),
        ];
    }

    /**
     * The dossier page the buyer lands on, with the current resume token and the flag the page already
     * reads: a renewal comes back to "my project", a SCALE audit to the Scale space, year 1 to the journey.
     */
    public function destinationFor(Payment $payment, bool $cancelled): string
    {
        $token = $payment->submission?->resume_token;

        [$route, $flag] = match ($payment->type) {
            PaymentType::AnnualRenewal => ['my-project', $cancelled ? 'renewal_cancelled' : 'renewal_return'],
            PaymentType::PackUpgrade => ['my-project', $cancelled ? 'upgrade_cancelled' : 'upgrade_return'],
            PaymentType::ScaleAudit => ['get-started.scale.space', $cancelled ? 'audit_cancelled' : 'audit_return'],
            PaymentType::StarterSubscription => ['get-started.starter.journey', $cancelled ? 'payment_cancelled' : 'payment_return'],
        };

        return route($route, ['dossier' => $token, $flag => 1]);
    }

    private function signedReturnUrl(Payment $payment, string $status): string
    {
        return url(URL::temporarySignedRoute(
            'get-started.payment.return',
            now()->addDays(self::SIGNATURE_TTL_DAYS),
            ['payment' => $payment->getKey(), 'status' => $status],
            absolute: false,
        ));
    }
}
