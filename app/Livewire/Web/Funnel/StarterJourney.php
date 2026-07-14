<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Starter\StartContractSigningAction;
use App\Actions\Web\Starter\StartStarterPaymentAction;
use App\Actions\Web\Starter\StoreStarterDocumentAction;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Multi-step STARTER dossier, driven by the submission status (source of truth): sign the mandate,
 * upload the required documents, pay. Signing and payment redirect out to the provider (Fake by
 * default) and come back to this same screen. Nothing is stored in the component beyond the current
 * upload; the step is always recomputed from the database.
 */
class StarterJourney extends Component
{
    use HandlesUnexpectedErrors;
    use WithFileUploads;

    public Submission $submission;

    /** @var array<string, TemporaryUploadedFile|null> Pending uploads keyed by document type value. */
    public array $documents = [];

    public string $paymentProvider = '';

    /** @var array<string, string> */
    public array $paymentOptions = [];

    public function mount(Submission $submission, PaymentGatewayRegistry $gateways): void
    {
        $this->submission = $submission;
        $this->paymentOptions = $gateways->options();
        $this->paymentProvider = (string) array_key_first($this->paymentOptions);
    }

    public function sign(StartContractSigningAction $startContractSigning): mixed
    {
        if ($this->step() !== 'sign') {
            return null;
        }

        try {
            $session = $startContractSigning->execute($this->submission);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('journey', $e->getUserMessage());

            return null;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'journey', 'STARTER contract signing');

            return null;
        }

        return $this->redirect($session->signingUrl);
    }

    public function uploadDocument(string $type, StoreStarterDocumentAction $storeStarterDocument): void
    {
        if ($this->step() !== 'documents' || ! $this->isRequiredMissingType($type)) {
            return;
        }

        $this->validate(
            ["documents.{$type}" => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ["documents.{$type}.required" => 'Please choose a file.', "documents.{$type}.*" => 'Please upload a PDF or image under 10 MB.'],
        );

        try {
            $storeStarterDocument->execute($this->submission, DocumentType::from($type), $this->documents[$type]);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('journey', $e->getUserMessage());

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'journey', 'STARTER document upload');

            return;
        }

        unset($this->documents[$type]);
        $this->submission->refresh();
    }

    public function pay(StartStarterPaymentAction $startStarterPayment): mixed
    {
        if ($this->step() !== 'payment') {
            return null;
        }

        try {
            $checkout = $startStarterPayment->execute($this->submission, $this->paymentProvider);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('journey', $e->getUserMessage());

            return null;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'journey', 'STARTER payment start');

            return null;
        }

        return $this->redirect($checkout->redirectUrl);
    }

    /**
     * The single source of truth for what the user sees, derived from the submission status.
     */
    private function step(): string
    {
        return match ($this->submission->status) {
            SubmissionStatus::InProgress => 'sign',
            SubmissionStatus::AwaitingDocuments => 'documents',
            SubmissionStatus::AwaitingPayment => 'payment',
            SubmissionStatus::Paid, SubmissionStatus::Completed => 'done',
            SubmissionStatus::Cancelled => 'cancelled',
            default => 'sign',
        };
    }

    /** @return list<DocumentType> */
    private function requiredDocumentTypes(): array
    {
        return array_map(
            static fn (string $value): DocumentType => DocumentType::from($value),
            (array) config('festilaw.starter.required_documents', []),
        );
    }

    private function isRequiredMissingType(string $type): bool
    {
        $required = array_map(static fn (DocumentType $t): string => $t->value, $this->requiredDocumentTypes());
        if (! in_array($type, $required, true)) {
            return false;
        }

        $present = $this->submission->uploadedDocuments->map(static fn ($d): string => $d->type->value)->all();

        return ! in_array($type, $present, true);
    }

    public function render(): View
    {
        $this->submission->load(['contract', 'uploadedDocuments']);

        $dossier = app(StarterDossierResolver::class)->resolve($this->submission);
        $presentTypes = $this->submission->uploadedDocuments->map(static fn ($d): string => $d->type->value)->all();

        return view('livewire.web.funnel.starter-journey', [
            'step' => $this->step(),
            'contractDeclined' => $this->submission->contract?->signature_status === SignatureStatus::Declined,
            'dossier' => $dossier,
            'requiredDocuments' => $this->requiredDocumentTypes(),
            'presentTypes' => $presentTypes,
            'amountCents' => (int) config('festilaw.starter.amount_cents'),
        ]);
    }
}
