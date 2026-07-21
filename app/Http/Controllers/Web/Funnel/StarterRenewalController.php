<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Starter\StartRenewalPaymentAction;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Starts an annual renewal payment from the client's "my project" space (POST). Resolves the dossier
 * from its capability token, kicks off the full-fee checkout, and redirects the visitor to the
 * provider. On a business error (nothing due, provider down) the visitor is sent back to the dossier
 * with a friendly message.
 */
final class StarterRenewalController extends Controller
{
    public function __construct(
        private readonly StartRenewalPaymentAction $startRenewal,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function __invoke(Request $request, Submission $dossier): RedirectResponse
    {
        abort_unless($dossier->type->hasOnlineJourney(), 404);

        $options = $this->gateways->options();
        $provider = (string) $request->input('provider', array_key_first($options) ?? '');

        try {
            $checkout = $this->startRenewal->execute($dossier, $provider);
        } catch (BaseAppException $e) {
            Log::warning($e->getMessage(), ['exception' => $e]);

            return redirect()
                ->route('my-project', ['dossier' => $dossier->resume_token])
                ->with('renewal_error', __($e->getUserMessage()));
        }

        return redirect($checkout->redirectUrl);
    }
}
