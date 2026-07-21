<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Enums\Contract\SignatureEventOutcome;
use App\Exceptions\Signature\SignatureException;
use App\Models\Contract;
use App\Models\Submission;
use App\Services\Contract\ContractPdfGenerator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Real SignWell adapter. Pay-per-use, no subscription: the first 25 documents/month are free and
 * test_mode documents are free and send no email · ideal to validate the integration before going
 * live. Per signing: renders the contract PDF (whose signing line carries invisible SignWell text tags),
 * creates a document with a single recipient, and returns the hosted signing URL to redirect the signer
 * to. The signature/date fields land on the contract line via the text tags (no appended page). Completion
 * is confirmed either by polling (checkStatus, on the signer's return · no webhook needed) or by the
 * HMAC-verified webhook (parseWebhook). The signed PDF (with its audit trail) is stored on the
 * private disk. Every technical error becomes a typed SignatureException; upstream stays
 * provider-agnostic.
 */
final class SignWellSignatureGateway implements SignatureGatewayInterface
{
    private const RECIPIENT_ID = '1';

    /** SignWell document statuses that mean the signer has fully completed the document. */
    private const COMPLETED_STATUSES = ['Completed', 'Manually completed'];

    /** @param  array<string, mixed>  $config */
    public function __construct(
        private readonly array $config,
        private readonly ContractPdfGenerator $pdfGenerator,
    ) {}

    public function key(): string
    {
        return 'signwell';
    }

    public function createSigningSession(Contract $contract): SigningSessionData
    {
        $this->assertConfigured(['api_key']);

        $submission = $contract->submission;
        $pdf = $this->pdfGenerator->generate($submission);

        $returnUrl = route('get-started.starter.journey', [
            'dossier' => $submission->resume_token,
            'signature_return' => 1,
        ]);

        $payload = [
            'test_mode' => $this->isTesting(),
            'name' => 'GPSR mandate - '.($submission->company_name ?: $this->signerName($contract)),
            'files' => [[
                'name' => 'mandate.pdf',
                'file_base64' => base64_encode($pdf),
            ]],
            // Non-embedded signing: SignWell emails the hosted signing link itself and returns it as
            // signing_url (which we redirect to). send_email is only accepted in embedded mode.
            'recipients' => [[
                'id' => self::RECIPIENT_ID,
                'name' => $this->signerName($contract),
                'email' => (string) $submission->email,
            ]],
            // Champs de signature/date poses par des text tags invisibles sur la ligne de signature du
            // contrat (cf. ContractPdfGenerator), plutot qu'une page de signature ajoutee a la fin.
            'text_tags' => true,
            'with_signature_page' => false,
            'redirect_url' => $returnUrl,
            'decline_redirect_url' => $returnUrl,
            'draft' => false,
            'reminders' => false,
            // Langue de la page de signature ET des emails envoyes par SignWell, selon le signataire.
            'language' => $this->signerLanguage($submission),
            'metadata' => ['submission_reference' => (string) $submission->reference],
        ];

        if (! empty($this->config['api_application_id'])) {
            $payload['api_application_id'] = (string) $this->config['api_application_id'];
        }

        try {
            $document = $this->api()->post('/documents', $payload)->throw()->json();
        } catch (RequestException $e) {
            // Surface SignWell's own error message (otherwise the wrapped exception hides why it failed).
            Log::warning('SignWell rejected the document creation.', [
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);
            throw SignatureException::apiRequestFailed('create document', $e);
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('create document', $e);
        }

        $documentId = (string) Arr::get($document, 'id', '');
        $signingUrl = (string) Arr::get($document, 'recipients.0.signing_url', '');
        if ($documentId === '' || $signingUrl === '') {
            throw SignatureException::apiRequestFailed('create document');
        }

        return new SigningSessionData(
            providerReference: $documentId,
            signingUrl: $signingUrl,
        );
    }

    public function currentSigningUrl(Contract $contract): ?string
    {
        $this->assertConfigured(['api_key']);

        $documentId = (string) ($contract->signature_provider_reference ?? '');
        if ($documentId === '') {
            return null;
        }

        try {
            $document = $this->api()->get("/documents/{$documentId}")->throw()->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('get document status', $e);
        }

        // Document deja fini (ou annule/refuse/expire) : plus signable, on recreera une session.
        $status = (string) Arr::get($document, 'status', '');
        if (in_array($status, [...self::COMPLETED_STATUSES, 'Declined', 'Canceled', 'Expired'], true)) {
            return null;
        }

        $url = (string) Arr::get($document, 'recipients.0.signing_url', '');

        return $url !== '' ? $url : null;
    }

    public function checkStatus(Contract $contract): SignatureWebhookData
    {
        $this->assertConfigured(['api_key']);

        $documentId = (string) ($contract->signature_provider_reference ?? '');
        if ($documentId === '') {
            return new SignatureWebhookData('', SignatureEventOutcome::Unresolved);
        }

        try {
            $document = $this->api()->get("/documents/{$documentId}")->throw()->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('get document status', $e);
        }

        return $this->resultFor($documentId, (string) Arr::get($document, 'status', ''));
    }

    public function parseWebhook(Request $request): SignatureWebhookData
    {
        $this->assertConfigured(['api_key']);
        $this->verifyWebhookSignature($request);

        return $this->resultFor(
            (string) $request->input('data.object.id', ''),
            (string) $request->input('data.object.status', ''),
        );
    }

    public function downloadSignedDocument(Contract $contract): ?string
    {
        $this->assertConfigured(['api_key']);

        $documentId = (string) ($contract->signature_provider_reference ?? '');
        if ($documentId === '') {
            return null;
        }

        try {
            $response = $this->api()
                ->get("/documents/{$documentId}/completed_pdf", ['audit_page' => 'true'])
                ->throw();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('download signed pdf', $e);
        }

        $path = "contracts/{$documentId}.pdf";
        Storage::disk('local')->put($path, $response->body());

        return $path;
    }

    /** Maps a SignWell document status to the provider-agnostic outcome (detection only — no download). */
    private function resultFor(string $documentId, string $status): SignatureWebhookData
    {
        return new SignatureWebhookData(
            providerReference: $documentId,
            outcome: $this->outcomeFor($documentId, $status),
        );
    }

    private function outcomeFor(string $documentId, string $status): SignatureEventOutcome
    {
        if ($documentId === '') {
            return SignatureEventOutcome::Unresolved;
        }

        return match (true) {
            in_array($status, self::COMPLETED_STATUSES, true) => SignatureEventOutcome::Signed,
            $status === 'Declined', $status === 'Canceled' => SignatureEventOutcome::Declined,
            $status === 'Expired' => SignatureEventOutcome::Expired,
            default => SignatureEventOutcome::Unresolved,
        };
    }

    private function signerName(Contract $contract): string
    {
        $submission = $contract->submission;
        $name = trim(($submission->first_name ?? '').' '.($submission->last_name ?? ''));

        return $name !== '' ? $name : (string) ($submission->company_name ?? 'Signer');
    }

    /**
     * Langue de la page de signature et des emails SignWell, calee sur la locale du signataire.
     * SignWell n'accepte qu'une liste finie de langues ; on retombe sur l'anglais sinon.
     */
    private function signerLanguage(Submission $submission): string
    {
        $locale = (string) ($submission->locale ?: config('app.locale'));

        return in_array($locale, ['en', 'fr', 'es'], true) ? $locale : 'en';
    }

    /**
     * SignWell signs each webhook with HMAC-SHA256 over the string "{event_type}@{event_time}" using
     * the API key as the secret (cf. developers.signwell.com · Event Hash Verification).
     */
    private function verifyWebhookSignature(Request $request): void
    {
        $type = (string) $request->input('event.type', '');
        $time = (string) $request->input('event.time', '');
        $received = (string) $request->input('event.hash', '');

        $expected = hash_hmac('sha256', "{$type}@{$time}", (string) $this->config['api_key']);

        if ($received === '' || ! hash_equals($expected, $received)) {
            throw SignatureException::webhookSignatureInvalid();
        }
    }

    private function api(): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => (string) $this->config['api_key']])
            ->baseUrl(rtrim((string) $this->config['api_base_url'], '/'))
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 200, throw: false);
    }

    private function isTesting(): bool
    {
        return (bool) ($this->config['test_mode'] ?? false);
    }

    /** @param  list<string>  $keys */
    private function assertConfigured(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($this->config[$key])) {
                throw SignatureException::providerNotConfigured('signwell');
            }
        }
    }
}
