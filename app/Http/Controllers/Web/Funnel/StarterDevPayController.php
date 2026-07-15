<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;

/**
 * DEV ONLY. Stands in for the payment provider's hosted checkout + webhook when the Fake driver is
 * used: confirms the pending payment (same Action the real webhook calls), then returns to the
 * dossier. Never reachable in production.
 */
final class StarterDevPayController extends Controller
{
    public function __invoke(string $locale, Submission $dossier, MarkPaymentSucceededAction $markPaymentSucceeded): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);

        $payment = $dossier->payments()
            ->where('status', PaymentStatus::Pending)
            ->latest()
            ->first();
        abort_if($payment === null, 404);

        $markPaymentSucceeded->execute($payment, $payment->provider_reference);

        // Paye : direction l'espace client "mon projet".
        return redirect()->route('my-project', ['locale' => app()->getLocale(), 'dossier' => $dossier->resume_token]);
    }
}
