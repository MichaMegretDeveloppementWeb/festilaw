<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The client's "my project" space · the hub for a STARTER dossier at ANY stage, reached by its magic
 * link ({dossier} binding, capability URL). It shows where the project stands (signed, documents, paid),
 * lets the visitor resume the funnel at the right step if unfinished, and once active exposes the plan,
 * next renewal and document downloads. Separate from the funnel itself (no sales aside).
 */
final class StarterProjectController extends Controller
{
    public function __construct(private readonly StarterDossierResolver $resolver) {}

    public function __invoke(string $locale, Submission $dossier): View
    {
        abort_unless($dossier->type === SubmissionType::Starter, 404);

        $dossier->loadMissing(['contract', 'uploadedDocuments']);
        $status = $this->resolver->resolve($dossier);
        $paid = in_array($dossier->status, [SubmissionStatus::Paid, SubmissionStatus::Completed], true);

        return view('web.my-project', [
            'submission' => $dossier,
            'signed' => $status->contractSigned,
            'documentsDone' => $status->missingDocuments === [],
            'paid' => $paid,
            'cancelled' => $dossier->status === SubmissionStatus::Cancelled,
            'renewsAt' => $paid ? $this->renewalDate($dossier) : null,
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
