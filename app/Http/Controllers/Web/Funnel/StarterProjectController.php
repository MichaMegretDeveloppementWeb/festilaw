<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Data\Web\Starter\MyProjectData;
use App\Data\Web\Starter\ProjectDocumentData;
use App\Enums\Billing\RenewalStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\UploadedDocument;
use App\Services\Billing\RenewalService;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

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

    public function __invoke(
        Request $request,
        Submission $dossier,
        PaymentGatewayRegistry $gateways,
        MarkPaymentSucceededAction $markPaymentSucceeded,
    ): View {
        abort_unless($dossier->type->hasOnlineJourney(), 404);

        // Retour d'un paiement de renouvellement : on confirme la synchrone au retour (le webhook reste
        // le filet cote serveur en prod ; en local il ne peut pas joindre le site).
        if ($request->boolean('renewal_return')) {
            $this->confirmPendingRenewal($dossier, $gateways, $markPaymentSucceeded);
        }

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

    /**
     * Confirme (au retour du checkout) le paiement de renouvellement en attente : on interroge le
     * provider (checkStatus) et, s'il est paye, on marque le paiement reussi. Erreur non bloquante :
     * on log et on laisse le webhook confirmer cote serveur en prod.
     */
    private function confirmPendingRenewal(
        Submission $dossier,
        PaymentGatewayRegistry $gateways,
        MarkPaymentSucceededAction $markPaymentSucceeded,
    ): void {
        // On interroge chaque paiement de renouvellement en attente (il peut en trainer plusieurs d'un
        // essai anterieur) et on confirme ceux reellement payes chez le provider.
        $pending = $dossier->payments()
            ->where('type', PaymentType::AnnualRenewal)
            ->where('status', PaymentStatus::Pending)
            ->get();

        foreach ($pending as $payment) {
            try {
                $event = $gateways->get((string) $payment->provider)->checkStatus($payment);

                if ($event->paid) {
                    $markPaymentSucceeded->execute($payment, $event->providerReference);
                }
            } catch (Throwable $e) {
                Log::channel('payments')->error('Renewal confirm-on-return failed.', ['exception' => $e, 'submission' => $dossier->id, 'payment' => $payment->id]);
            }
        }
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
