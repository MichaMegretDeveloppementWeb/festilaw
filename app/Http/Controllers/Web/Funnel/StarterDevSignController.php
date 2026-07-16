<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;

/**
 * DEV ONLY. Stands in for the signature provider's hosted page + webhook when the Fake driver is
 * used: marks the contract signed (same Action the real webhook calls), then returns to the dossier.
 * Never reachable in production.
 */
final class StarterDevSignController extends Controller
{
    public function __invoke(Submission $dossier, MarkContractSignedAction $markContractSigned): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);

        $contract = $dossier->contract;
        abort_if($contract === null, 404);

        $markContractSigned->execute($contract, null, $contract->signature_provider_reference);

        return redirect()
            ->route('get-started.starter.journey', ['dossier' => $dossier->resume_token])
            ->with('starter_status', 'signed');
    }
}
