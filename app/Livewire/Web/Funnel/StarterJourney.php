<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Actions\Web\Starter\StartContractSigningAction;
use App\Actions\Web\Starter\StartStarterPaymentAction;
use App\Actions\Web\Starter\SubmitStarterDocumentsAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Multi-step STARTER dossier, driven by the submission status (source of truth): sign the mandate,
 * drop the required documents, pay. Signing and payment redirect out to the provider (Fake by
 * default) and come back to this same screen. Documents are staged in the component (drag & drop)
 * and only persisted when the visitor confirms with a single action; the step is always recomputed
 * from the database.
 */
class StarterJourney extends Component
{
    use HandlesUnexpectedErrors;
    use WithFileUploads;

    /** Bounds the "confirming payment" auto-refresh loop (~2 min at 5s before the email fallback). */
    private const MAX_PAYMENT_POLLS = 24;

    public Submission $submission;

    /** @var array<string, TemporaryUploadedFile|null> Staged uploads keyed by document type value. */
    public array $documents = [];

    public string $paymentProvider = '';

    /** @var array<string, string> */
    public array $paymentOptions = [];

    /** Auto-confirm the signature on load when a session is already in flight (provider return OR resume). */
    public bool $autoConfirm = false;

    /** How many payment-status polls have run on this dossier (drives + bounds the confirming loop). */
    public int $paymentChecks = 0;

    /** Client details printed on the mandate, captured on the sign step and stored in the contract's filled_fields. */
    public string $incorporationPlace = '';

    public string $foundingYear = '';

    public string $activity = '';

    public function mount(Submission $submission, PaymentGatewayRegistry $gateways): void
    {
        $this->submission = $submission;
        $this->paymentOptions = $gateways->options();
        $this->paymentProvider = (string) array_key_first($this->paymentOptions);
        $this->autoConfirm = $this->signatureInFlight();
        $this->loadMandateDetails();
    }

    /** Pre-fills the mandate-detail fields from what was already saved (resume / going back). */
    private function loadMandateDetails(): void
    {
        $this->submission->loadMissing('contract');
        $fields = $this->submission->contract?->filled_fields ?? [];
        $this->incorporationPlace = (string) ($fields['incorporation_place'] ?? '');
        $this->foundingYear = (string) ($fields['founding_year'] ?? '');
        $this->activity = (string) ($fields['activity'] ?? '');
    }

    /** A signing session already exists for this dossier but the contract is not signed yet. */
    private function signatureInFlight(): bool
    {
        $this->submission->loadMissing('contract');
        $contract = $this->submission->contract;

        return $this->step() === 'sign'
            && $contract !== null
            && (string) ($contract->signature_provider_reference ?? '') !== '';
    }

    /** The pending payment whose checkout is in flight, if any. */
    private function pendingPayment(): ?Payment
    {
        return $this->submission->payments()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('provider_reference')
            ->latest()
            ->first();
    }

    /**
     * Manual confirmation (the "I have signed" button): polls the provider and advances if signed,
     * otherwise tells the signer it is not recorded yet.
     */
    public function confirmSignature(SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markContractSigned): void
    {
        try {
            if (! $this->tryConfirmSignature($signatureGateway, $markContractSigned) && $this->step() === 'sign') {
                $this->addError('journey', __('Your signature has not been recorded yet. If you have just signed, wait a few seconds and check again.'));
            }
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('journey', __($e->getUserMessage()));
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'journey', 'STARTER signature confirmation');
        }
    }

    /**
     * Silent auto-confirmation on return/resume (wire:init): advances if the provider already has the
     * signature, otherwise does nothing (the signer can still sign or check manually). Self-heals the
     * "signed but the browser closed before the redirect completed" case.
     */
    public function autoConfirmSignature(SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markContractSigned): void
    {
        $this->autoConfirm = false;

        try {
            $this->tryConfirmSignature($signatureGateway, $markContractSigned);
        } catch (Throwable $e) {
            // Best effort : un echec ne montre rien ici, le signataire garde le bouton manuel.
            Log::error('STARTER auto signature confirmation failed.', ['exception' => $e]);
        }
    }

    /**
     * Polls the provider once; if the signature is complete, records it (idempotent), advances the
     * dossier and flashes the success banner. Returns whether the signature was confirmed.
     */
    private function tryConfirmSignature(SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markContractSigned): bool
    {
        if ($this->step() !== 'sign') {
            return false;
        }

        $contract = $this->submission->contract;
        if ($contract === null || (string) ($contract->signature_provider_reference ?? '') === '') {
            return false;
        }

        $event = $signatureGateway->checkStatus($contract);
        if (! $event->signed) {
            return false;
        }

        $markContractSigned->execute($contract, $event->signedFilePath, $event->providerReference);
        $this->submission->refresh();

        // Bandeau de succes, comme le retour "fake" (StarterDevSignController) mais cote Livewire :
        // now() = visible dans ce re-render uniquement, sans fuiter a l'interaction suivante.
        session()->now('starter_status', 'signed');

        return true;
    }

    public function sign(StartContractSigningAction $startContractSigning, SignatureGatewayInterface $signatureGateway): mixed
    {
        if ($this->step() !== 'sign') {
            return null;
        }

        // Reprise : reutiliser la session de signature deja en cours plutot que d'en creer une 2e
        // (evite un document en double chez le prestataire et un 2e email au signataire).
        $existingUrl = $this->existingSigningUrl($signatureGateway);
        if ($existingUrl !== null) {
            return $this->redirect($existingUrl);
        }

        // Nouvelle session : on fige d'abord les informations imprimees sur le mandat (le PDF est
        // genere a partir de la, cf. ContractPdfGenerator).
        $this->validate($this->mandateRules(), $this->mandateMessages());
        $this->saveMandateDetails();

        try {
            $session = $startContractSigning->execute($this->submission);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('journey', __($e->getUserMessage()));

            return null;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'journey', 'STARTER contract signing');

            return null;
        }

        return $this->redirect($session->signingUrl);
    }

    /** The in-flight signing URL to reuse on resume, or null to start a fresh session. */
    private function existingSigningUrl(SignatureGatewayInterface $signatureGateway): ?string
    {
        $contract = $this->submission->contract;
        if ($contract === null || (string) ($contract->signature_provider_reference ?? '') === '') {
            return null;
        }

        try {
            $url = $signatureGateway->currentSigningUrl($contract);

            return ($url !== null && $url !== '') ? $url : null;
        } catch (Throwable $e) {
            // Provider indisponible : on retombe sur la creation d'une nouvelle session.
            Log::warning('STARTER could not reuse the signing session, creating a new one.', ['exception' => $e]);

            return null;
        }
    }

    /** @return array<string, array<int, string>> */
    private function mandateRules(): array
    {
        return [
            'incorporationPlace' => ['required', 'string', 'max:160'],
            'foundingYear' => ['required', 'regex:/^[0-9]{4}$/'],
            'activity' => ['required', 'string', 'max:400'],
        ];
    }

    /** @return array<string, string> */
    private function mandateMessages(): array
    {
        return [
            'incorporationPlace.required' => __('Please enter the city and country where your company is registered.'),
            'foundingYear.required' => __('Please enter the year your company was founded.'),
            'foundingYear.regex' => __('Please enter a 4-digit year (e.g. 2015).'),
            'activity.required' => __('Please describe your main business activity.'),
        ];
    }

    /** Freezes the mandate details on the contract so the generated PDF is filled in. */
    private function saveMandateDetails(): void
    {
        $contract = $this->submission->contract;
        if ($contract === null) {
            return;
        }

        $contract->update([
            'filled_fields' => array_merge($contract->filled_fields ?? [], [
                'incorporation_place' => trim($this->incorporationPlace),
                'founding_year' => trim($this->foundingYear),
                'activity' => trim($this->activity),
            ]),
        ]);
    }

    /** Removes a staged (not yet persisted) document before submitting. */
    public function removeDocument(string $type): void
    {
        unset($this->documents[$type]);
    }

    /** Validates every required document is staged + valid, then persists them all and advances. */
    public function submitDocuments(SubmitStarterDocumentsAction $submitStarterDocuments): void
    {
        if ($this->step() !== 'documents') {
            return;
        }

        $required = $this->requiredDocumentTypes();
        $deposited = array_filter($this->documents);

        // Presence : erreur SOUS chaque document manquant (pas de message global au-dessus).
        $hasMissing = false;
        foreach ($required as $t) {
            if (! isset($deposited[$t->value])) {
                $this->addError("documents.{$t->value}", __('This document is required.'));
                $hasMissing = true;
            }
        }
        if ($hasMissing) {
            return;
        }

        // Forme : chaque fichier depose (erreur affichee sous le document concerne).
        $mimes = implode(',', $this->documentMimes());
        $rules = [];
        $messages = [];
        foreach ($required as $t) {
            $rules["documents.{$t->value}"] = ['required', 'file', "mimes:{$mimes}", 'max:10240'];
            $messages["documents.{$t->value}.mimes"] = __('Accepted formats: PDF, JPG, PNG or WEBP.');
            $messages["documents.{$t->value}.max"] = __('This file is too large (10 MB maximum).');
        }
        $this->validate($rules, $messages);

        try {
            $submitStarterDocuments->execute($this->submission, $deposited);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('documents_submit', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'documents_submit', 'STARTER documents submit');

            return;
        }

        $this->reset('documents');
        $this->submission->refresh();
    }

    public function pay(StartStarterPaymentAction $startStarterPayment, PaymentGatewayRegistry $gateways): mixed
    {
        if ($this->step() !== 'payment') {
            return null;
        }

        // Reprise : reutiliser la session de paiement deja en cours plutot que d'en creer une 2e
        // (anti double-debit, crucial avec les moyens de paiement asynchrones).
        $existingUrl = $this->existingCheckoutUrl($gateways);
        if ($existingUrl !== null) {
            return $this->redirect($existingUrl);
        }

        try {
            $checkout = $startStarterPayment->execute($this->submission, $this->paymentProvider);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('journey', __($e->getUserMessage()));

            return null;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'journey', 'STARTER payment start');

            return null;
        }

        return $this->redirect($checkout->redirectUrl);
    }

    /** The in-flight checkout URL to reuse on resume, or null to start a fresh checkout. */
    private function existingCheckoutUrl(PaymentGatewayRegistry $gateways): ?string
    {
        $payment = $this->pendingPayment();
        if ($payment === null || ! $gateways->has($payment->provider)) {
            return null;
        }

        try {
            $url = $gateways->get($payment->provider)->currentCheckoutUrl($payment);

            return ($url !== null && $url !== '') ? $url : null;
        } catch (Throwable $e) {
            // Provider indisponible : on retombe sur la creation d'une nouvelle session.
            Log::warning('STARTER could not reuse the checkout session, creating a new one.', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Auto-refresh loop (wire:init + wire:poll): silently polls the provider until the payment settles,
     * then advances + flashes the success banner. Bounded by MAX_PAYMENT_POLLS · past that, the UI shows
     * the "we'll email you" fallback. Lets asynchronous methods settle without ever blocking the buyer.
     */
    public function pollPayment(PaymentGatewayRegistry $gateways, MarkPaymentSucceededAction $markPaymentSucceeded): mixed
    {
        $this->paymentChecks++;

        try {
            $this->tryConfirmPayment($gateways, $markPaymentSucceeded);
        } catch (Throwable $e) {
            // Best effort : un echec de poll n'affiche rien, le webhook reste la source de verite.
            Log::error('STARTER payment poll failed.', ['exception' => $e]);
        }

        // Paiement confirme : on quitte le parcours pour l'espace client "mon projet".
        if ($this->step() === 'done') {
            return $this->redirect(route('my-project', [
                'dossier' => $this->submission->resume_token,
            ]));
        }

        return null;
    }

    /**
     * Polls the provider once; if the payment is settled, records it (idempotent), advances the
     * dossier and flashes the success banner. Returns whether the payment was confirmed.
     */
    private function tryConfirmPayment(PaymentGatewayRegistry $gateways, MarkPaymentSucceededAction $markPaymentSucceeded): bool
    {
        if ($this->step() !== 'payment') {
            return false;
        }

        $payment = $this->pendingPayment();
        if ($payment === null || ! $gateways->has($payment->provider)) {
            return false;
        }

        $event = $gateways->get($payment->provider)->checkStatus($payment);
        if (! $event->paid) {
            return false;
        }

        $markPaymentSucceeded->execute($payment, $event->providerReference);
        $this->submission->refresh();

        session()->now('starter_status', 'paid');

        return true;
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

    /**
     * Allowed upload extensions, single source of truth for the validation rule, the input's accept
     * attribute and the on-screen hint.
     *
     * @return list<string>
     */
    private function documentMimes(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    }

    /**
     * Display metadata for staged files (name + size), read defensively so a metadata hiccup on the
     * temporary file never breaks the render.
     *
     * @return array<string, array{name: string, size: int|null}>
     */
    private function stagedDocuments(): array
    {
        $meta = [];
        foreach ($this->documents as $type => $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            try {
                $size = $file->getSize();
            } catch (Throwable) {
                $size = null;
            }

            $meta[$type] = ['name' => $file->getClientOriginalName(), 'size' => $size];
        }

        return $meta;
    }

    public function render(): View
    {
        $this->submission->loadMissing('contract');

        return view('livewire.web.funnel.starter-journey', [
            'step' => $this->step(),
            'contractDeclined' => $this->submission->contract?->signature_status === SignatureStatus::Declined,
            'signatureStarted' => (string) ($this->submission->contract?->signature_provider_reference ?? '') !== '',
            'paymentStarted' => $this->pendingPayment() !== null,
            'paymentTimedOut' => $this->paymentChecks >= self::MAX_PAYMENT_POLLS,
            'requiredDocuments' => $this->requiredDocumentTypes(),
            'deposits' => $this->stagedDocuments(),
            'acceptAttr' => '.'.implode(',.', $this->documentMimes()),
            'amountCents' => (int) config('festilaw.starter.amount_cents'),
            'myProjectUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
        ]);
    }
}
