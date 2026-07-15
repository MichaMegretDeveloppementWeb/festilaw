<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The client's own "my file" space · a page dedicated to an ACTIVE (paid) STARTER dossier, separate
 * from the sales funnel (no aside). Accessed by the dossier's magic link ({dossier} binding). A dossier
 * that is not paid yet is sent back to the funnel to finish the process.
 */
final class StarterDossierController extends Controller
{
    private const ACTIVE_STATUSES = [SubmissionStatus::Paid, SubmissionStatus::Completed];

    public function __invoke(string $locale, Submission $dossier): View|RedirectResponse
    {
        abort_unless($dossier->type === SubmissionType::Starter, 404);

        if (! in_array($dossier->status, self::ACTIVE_STATUSES, true)) {
            return redirect()->route('get-started.starter.journey', [
                'locale' => $locale,
                'dossier' => $dossier->resume_token,
            ]);
        }

        $dossier->loadMissing(['contract', 'uploadedDocuments']);

        return view('web.my-file', [
            'submission' => $dossier,
            'renewsAt' => $this->renewalDate($dossier),
        ]);
    }

    /** Annual pack: next renewal = the last successful payment + 1 year (recurring billing not set up yet). */
    private function renewalDate(Submission $dossier): ?Carbon
    {
        $lastPaidAt = $dossier->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->latest('paid_at')
            ->value('paid_at');

        return $lastPaidAt?->copy()->addYear();
    }
}
