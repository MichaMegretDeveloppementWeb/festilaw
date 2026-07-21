<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Data\Web\Starter\MyProjectData;
use App\Data\Web\Starter\ProjectDocumentData;
use App\Enums\Billing\RenewalStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\UploadedDocument;
use App\Services\Billing\RenewalService;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\View\View;

/**
 * The client's "my project" space · the hub for a self-service dossier (Creator or Pro) at ANY stage,
 * reached by its magic link ({dossier} binding, capability URL). It shows where the project stands
 * (signed, documents, paid), lets the visitor resume the funnel at the right step if unfinished, and
 * once active exposes the plan, next renewal (with a pay-renewal call to action when due) and document
 * downloads. Separate from the funnel itself (no sales aside).
 */
final class StarterProjectController extends Controller
{
    public function __construct(
        private readonly StarterDossierResolver $resolver,
        private readonly RenewalService $renewals,
    ) {}

    public function __invoke(Submission $dossier): View
    {
        abort_unless($dossier->type->hasOnlineJourney(), 404);

        $dossier->loadMissing(['contract', 'uploadedDocuments', 'payments']);
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
        $hasCountersigned = (string) ($dossier->contract?->countersigned_file_path ?? '') !== '';
        $lastPayment = $paid ? $this->lastSuccessfulPayment($dossier) : null;

        $renewalStatus = $paid ? $this->renewals->status($dossier) : RenewalStatus::UpToDate;
        $renewalYear = $paid ? $this->renewals->dueYear($dossier) : null;

        $project = new MyProjectData(
            reference: (string) $dossier->reference,
            packLabel: $dossier->type->label(),
            annualCents: $dossier->type->annualCents(),
            signed: $signed,
            documentsDone: $status->missingDocuments === [],
            paid: $paid,
            cancelled: $dossier->status === SubmissionStatus::Cancelled,
            renewsAt: $this->renewals->nextRenewalDate($dossier),
            renewalDue: $renewalYear !== null,
            renewalOverdue: $renewalStatus === RenewalStatus::Overdue,
            renewalYear: $renewalYear,
            renewUrl: route('get-started.starter.renew', ['dossier' => $dossier->resume_token]),
            paidAmountCents: $lastPayment?->amount_cents,
            paidAt: $lastPayment?->paid_at,
            resumeUrl: route('get-started.starter.journey', ['dossier' => $dossier->resume_token]),
            mandateDownloadUrl: $hasSignedMandate
                ? route('get-started.starter.mandate', ['dossier' => $dossier->resume_token])
                : null,
            countersignedDownloadUrl: $hasCountersigned
                ? route('get-started.starter.countersigned', ['dossier' => $dossier->resume_token])
                : null,
            euRpAddress: $dossier->eu_rp_address ?: null,
            documents: $documents,
        );

        return view('web.my-project', ['project' => $project]);
    }

    /** Last successful payment on the dossier (most recent by pay date), or null if none. */
    private function lastSuccessfulPayment(Submission $dossier): ?Payment
    {
        return $dossier->payments
            ->where('status', PaymentStatus::Succeeded)
            ->sortByDesc('paid_at')
            ->first();
    }
}
