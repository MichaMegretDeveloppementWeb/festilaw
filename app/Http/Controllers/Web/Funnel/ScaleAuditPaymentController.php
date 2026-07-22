<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Scale\StartScaleAuditPaymentAction;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts the SCALE audit payment (75 EUR) from the client's Scale space (POST). Kicks off the checkout and
 * redirects to the provider. On a business error (already paid, provider down) the visitor is sent back to
 * the space with a friendly message. Mirrors StarterRenewalController.
 */
final class ScaleAuditPaymentController extends Controller
{
    public function __construct(
        private readonly StartScaleAuditPaymentAction $startAudit,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function __invoke(Request $request, Submission $dossier): RedirectResponse
    {
        abort_unless($dossier->type === SubmissionType::Scale, 404);

        $options = $this->gateways->options();
        $provider = (string) $request->input('provider', array_key_first($options) ?? '');

        try {
            $checkout = $this->startAudit->execute($dossier, $provider);
        } catch (BaseAppException $e) {
            Log::channel('payments')->warning($e->getMessage(), ['exception' => $e, 'submission' => $dossier->id]);

            return $this->backToSpace($dossier, __($e->getUserMessage()));
        } catch (Throwable $e) {
            // Dernier filet : aucune erreur inattendue (driver BDD, SDK de paiement, PHP) ne doit
            // atteindre le client. On log le detail complet et on affiche un message generique.
            Log::channel('payments')->error($e->getMessage(), ['exception' => $e, 'submission' => $dossier->id]);

            return $this->backToSpace($dossier, __('Something went wrong on our end. Please try again. If the problem persists, contact us.'));
        }

        return redirect($checkout->redirectUrl);
    }

    private function backToSpace(Submission $dossier, string $message): RedirectResponse
    {
        return redirect()
            ->route('get-started.scale.space', ['dossier' => $dossier->resume_token])
            ->with('scale_error', $message);
    }
}
