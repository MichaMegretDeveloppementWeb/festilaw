<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landing point of the payment provider's success/cancel URLs (signed route, cf. PaymentReturnService):
 * sends the buyer back to the dossier page with its CURRENT resume token, so a link resent while paying
 * no longer strands them on a 404. The signature (checked by the `signed` middleware) is the access guard.
 */
final class PaymentReturnController extends Controller
{
    public function __construct(private readonly PaymentReturnService $returns) {}

    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->submission === null, 404);

        return redirect()->to($this->returns->destinationFor($payment, $request->query('status') === 'cancelled'));
    }
}
