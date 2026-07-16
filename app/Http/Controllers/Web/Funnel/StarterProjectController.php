<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Data\Web\Starter\MyProjectData;
use App\Data\Web\Starter\ProjectDocumentData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Http\Controllers\Controller;
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

        $project = new MyProjectData(
            reference: (string) $dossier->reference,
            signed: $signed,
            documentsDone: $status->missingDocuments === [],
            paid: $paid,
            cancelled: $dossier->status === SubmissionStatus::Cancelled,
            renewsAt: $paid ? $this->renewalDate($dossier) : null,
            resumeUrl: route('get-started.starter.journey', ['dossier' => $dossier->resume_token]),
            mandateDownloadUrl: $hasSignedMandate
                ? route('get-started.starter.mandate', ['dossier' => $dossier->resume_token])
                : null,
            documents: $documents,
        );

        return view('web.my-project', ['project' => $project]);
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
