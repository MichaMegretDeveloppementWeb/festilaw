<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Webhook;

use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives the signature provider webhook (Zoho...), verifies + parses it via the active gateway,
 * and records the signature synchronously. Idempotent (the Action is), no worker/cron.
 */
final class SignatureWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        SignatureGatewayInterface $gateway,
        MarkContractSignedAction $markContractSigned,
    ): Response {
        try {
            $event = $gateway->parseWebhook($request);
        } catch (BaseAppException $e) {
            Log::warning($e->getMessage(), ['exception' => $e]);

            return response()->noContent(400);
        }

        $contract = Contract::query()
            ->where('signature_provider_reference', $event->providerReference)
            ->first();

        if ($contract !== null && $event->signed) {
            $markContractSigned->execute($contract, $event->signedFilePath, $event->providerReference);
        }

        return response()->noContent();
    }
}
