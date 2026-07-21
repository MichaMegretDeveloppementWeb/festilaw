<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Data\Web\Starter\MyProjectData;
use App\Data\Web\Starter\ProjectDocumentData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\UploadedDocument;
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

    public function __invoke(Submission $dossier): View
    {
        abort_unless($dossier->type === SubmissionType::Starter, 404);

        $dossier->loadMissing(['contract', 'uploadedDocuments']);
        $status = $this->resolver->resolve($dossier);
        $signed = $status->contractSigned;
        $paid = in_array($dossier->status, [SubmissionStatus::Paid, SubmissionStatus::Completed], true);

        $documents = $dossier->uploadedDocuments
            ->map(fn (UploadedDocument $doc): ProjectDocumentData => new ProjectDocumentData(
                label: $doc->type->label(),
                filename: (string) $doc->original_filename,
                downloadUrl: route('get-started.starter.document', ['dossier' => $dossier->resume_token, 'document' => $doc->id]),
            ))
            ->all();

        $hasSignedMandate = $signed && (string) ($dossier->contract?->signed_file_path ?? '') !== '';
        $lastPayment = $paid ? $this->lastSuccessfulPayment($dossier) : null;

        $project = new MyProjectData(
            reference: (string) $dossier->reference,
            signed: $signed,
            documentsDone: $status->missingDocuments === [],
            paid: $paid,
            cancelled: $dossier->status === SubmissionStatus::Cancelled,
            renewsAt: $this->renewalDate($lastPayment),
            paidAmountCents: $lastPayment?->amount_cents,
            paidAt: $lastPayment?->paid_at,
            resumeUrl: route('get-started.starter.journey', ['dossier' => $dossier->resume_token]),
            mandateDownloadUrl: $hasSignedMandate
                ? route('get-started.starter.mandate', ['dossier' => $dossier->resume_token])
                : null,
            euRpAddress: $dossier->eu_rp_address ?: null,
            documents: $documents,
        );

        return view('web.my-project', ['project' => $project]);
    }

    /** Last successful payment on the dossier (most recent by pay date), or null if none. */
    private function lastSuccessfulPayment(Submission $dossier): ?Payment
    {
        return $dossier->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->latest('paid_at')
            ->first();
    }

    /**
     * Next renewal date. A service year runs 1 January to 31 December, the first year is billed pro rata,
     * and every following year is invoiced each January. So the next renewal is always 1 January of the
     * year after the last successful payment, whatever the pay date within its year.
     */
    private function renewalDate(?Payment $lastPayment): ?Carbon
    {
        return $lastPayment?->paid_at?->copy()->startOfYear()->addYear();
    }
}
