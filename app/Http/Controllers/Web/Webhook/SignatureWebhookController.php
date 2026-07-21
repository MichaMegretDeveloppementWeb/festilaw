<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Webhook;

use App\Actions\Web\Starter\MarkContractDeclinedAction;
use App\Actions\Web\Starter\MarkContractExpiredAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureEventOutcome;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives the signature provider webhook (SignWell...), verifies + parses it via the active gateway,
 * and records the outcome synchronously (signed / declined / expired). Idempotent (the Actions are),
 * no worker/cron.
 */
final class SignatureWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        SignatureGatewayInterface $gateway,
        MarkContractSignedAction $markContractSigned,
        MarkContractDeclinedAction $markContractDeclined,
        MarkContractExpiredAction $markContractExpired,
    ): Response {
        try {
            $event = $gateway->parseWebhook($request);
        } catch (BaseAppException $e) {
            Log::channel('signature')->warning($e->getMessage(), ['exception' => $e]);

            return response()->noContent(400);
        }

        try {
            $contract = Contract::query()
                ->where('signature_provider_reference', $event->providerReference)
                ->first();

            if ($contract !== null) {
                match ($event->outcome) {
                    SignatureEventOutcome::Signed => $markContractSigned->execute($contract, $event->providerReference),
                    SignatureEventOutcome::Declined => $markContractDeclined->execute($contract),
                    SignatureEventOutcome::Expired => $markContractExpired->execute($contract),
                    SignatureEventOutcome::Unresolved => null,
                };
            }
        } catch (Throwable $e) {
            // Erreur inattendue cote traitement : on trace et on repond 500 pour que le provider reessaie.
            Log::channel('signature')->error('Signature webhook processing failed.', ['exception' => $e]);

            return response()->noContent(500);
        }

        return response()->noContent();
    }
}
