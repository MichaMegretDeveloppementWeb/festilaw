<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Analytics\AudienceEvent;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landing point of the payment provider's success/cancel URLs (signed route, cf. PaymentReturnService):
 * sends the buyer back to the dossier page with its CURRENT resume token, so a link resent while paying
 * no longer strands them on a 404. The signature (checked by the `signed` middleware) is the access guard.
 *
 * C'est aussi la qu'un paiement se compte pour l'audience (plan SC8) : le webhook arrive souvent avant le
 * visiteur, mais ce retour a toujours lieu dans sa requete. Stripe n'y renvoie qu'une fois le paiement
 * termine chez lui ; un second passage par la meme adresse (bouton Precedent) ne compte pas deux fois.
 */
final class PaymentReturnController extends Controller
{
    public function __construct(
        private readonly PaymentReturnService $returns,
        private readonly AudienceTracker $audience,
    ) {}

    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->submission === null, 404);

        $cancelled = $request->query('status') === 'cancelled';
        $this->audience->recordOnce(
            $cancelled ? AudienceEvent::PaymentCancelled : AudienceEvent::paidFor($payment->type, $payment->submission->type),
            "payment:{$payment->id}",
        );

        return redirect()->to($this->returns->destinationFor($payment, $cancelled));
    }
}
