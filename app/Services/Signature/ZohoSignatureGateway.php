<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Data\Signature\SigningSessionData;
use App\Exceptions\Signature\SignatureException;
use App\Models\Contract;
use App\Services\Contract\ContractPdfGenerator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Real Zoho Sign adapter (EU datacenter by default), "upload document" flow · works without the
 * paid Templates feature. Per signing: renders the contract PDF, uploads it as a new request with an
 * embedded signer, sends it, and returns the one-time signing URL to redirect to. Completion is
 * confirmed either by polling (checkStatus, on the signer's return · no tunnel needed) or by the
 * HMAC-verified webhook (parseWebhook · production). The signed PDF is stored on the private disk.
 * Every technical error becomes a typed SignatureException; upstream stays provider-agnostic.
 */
final class ZohoSignatureGateway implements SignatureGatewayInterface
{
    /** @param  array<string, mixed>  $config */
    public function __construct(
        private readonly array $config,
        private readonly ZohoTokenProvider $tokenProvider,
        private readonly ContractPdfGenerator $pdfGenerator,
    ) {}

    public function key(): string
    {
        return 'zoho';
    }

    public function createSigningSession(Contract $contract): SigningSessionData
    {
        $this->assertConfigured(['client_id', 'client_secret', 'refresh_token']);

        $submission = $contract->submission;
        $signerName = $this->signerName($contract);
        $pdf = $this->pdfGenerator->generate($submission);

        $returnUrl = route('get-started.starter.journey', [
            'locale' => $submission->locale ?: config('app.locale'),
            'dossier' => $submission->resume_token,
            'signature_return' => 1,
        ]);

        $createData = json_encode([
            'requests' => [
                'request_name' => 'GPSR mandate - '.($submission->company_name ?: $signerName),
                'expiration_days' => 15,
                'actions' => [$this->signerAction($contract)],
                'redirect_pages' => ['sign_success' => $returnUrl, 'sign_completed' => $returnUrl],
            ],
        ], JSON_THROW_ON_ERROR);

        // 1. Create the request by uploading the generated PDF (leaves a draft).
        try {
            $created = $this->api()
                ->attach('file', $pdf, 'mandate.pdf')
                ->post('/requests', ['data' => $createData])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('create request', $e);
        }

        $requestId = (string) Arr::get($created, 'requests.request_id', '');
        $actionId = (string) Arr::get($created, 'requests.actions.0.action_id', '');
        if ($requestId === '' || $actionId === '') {
            throw SignatureException::apiRequestFailed('create request');
        }

        // 2. Send it. testing=true in dev: watermarked, does not consume credits.
        $submitData = json_encode([
            'requests' => ['actions' => [$this->signerAction($contract, $actionId)]],
        ], JSON_THROW_ON_ERROR);

        try {
            $this->api()
                ->asForm()
                ->post("/requests/{$requestId}/submit".($this->isTesting() ? '?testing=true' : ''), ['data' => $submitData])
                ->throw();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('submit request', $e);
        }

        // 3. One-time embedded signing URL to redirect the signer to.
        return new SigningSessionData(
            providerReference: $requestId,
            signingUrl: $this->embeddedSigningUrl($requestId, $actionId),
        );
    }

    public function checkStatus(Contract $contract): SignatureWebhookData
    {
        $this->assertConfigured(['client_id', 'client_secret', 'refresh_token']);

        $requestId = (string) ($contract->signature_provider_reference ?? '');
        if ($requestId === '') {
            return new SignatureWebhookData('', false, null);
        }

        try {
            $details = $this->api()->get("/requests/{$requestId}")->throw()->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('get request status', $e);
        }

        $signed = Arr::get($details, 'requests.request_status') === 'completed';

        return new SignatureWebhookData(
            providerReference: $requestId,
            signed: $signed,
            signedFilePath: $signed ? $this->downloadSignedPdf($requestId) : null,
        );
    }

    public function parseWebhook(Request $request): SignatureWebhookData
    {
        $this->assertConfigured(['webhook_secret']);
        $this->verifyWebhookSignature($request);

        $operation = (string) $request->input('notifications.operation_type', '');
        $requestId = (string) $request->input('requests.request_id', '');

        $signed = $operation === 'RequestCompleted'
            && (string) $request->input('requests.request_status', '') === 'completed'
            && $requestId !== '';

        return new SignatureWebhookData(
            providerReference: $requestId,
            signed: $signed,
            signedFilePath: $signed ? $this->downloadSignedPdf($requestId) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function signerAction(Contract $contract, ?string $actionId = null): array
    {
        $submission = $contract->submission;

        $action = [
            'action_type' => 'SIGN',
            'recipient_name' => $this->signerName($contract),
            'recipient_email' => (string) $submission->email,
            'signing_order' => 0,
            'is_embedded' => true,
            'verify_recipient' => false,
        ];

        if ($actionId !== null) {
            $action['action_id'] = $actionId;
        }

        return $action;
    }

    private function signerName(Contract $contract): string
    {
        $submission = $contract->submission;
        $name = trim(($submission->first_name ?? '').' '.($submission->last_name ?? ''));

        return $name !== '' ? $name : (string) ($submission->company_name ?? 'Signer');
    }

    private function embeddedSigningUrl(string $requestId, string $actionId): string
    {
        $host = rtrim((string) config('app.url'), '/');

        try {
            $response = $this->api()
                ->post("/requests/{$requestId}/actions/{$actionId}/embedtoken?host=".urlencode($host))
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('embedded signing url', $e);
        }

        $signUrl = (string) Arr::get($response, 'sign_url', '');
        if ($signUrl === '') {
            throw SignatureException::apiRequestFailed('embedded signing url');
        }

        return $signUrl;
    }

    private function downloadSignedPdf(string $requestId): string
    {
        try {
            $response = $this->api()->get("/requests/{$requestId}/pdf", ['merge' => 'true'])->throw();
        } catch (Throwable $e) {
            throw SignatureException::apiRequestFailed('download signed pdf', $e);
        }

        $path = "contracts/{$requestId}.pdf";
        Storage::disk('local')->put($path, $response->body());

        return $path;
    }

    private function verifyWebhookSignature(Request $request): void
    {
        $secret = (string) $this->config['webhook_secret'];
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($expected, (string) $request->header('X-ZS-WEBHOOK-SIGNATURE', ''))) {
            throw SignatureException::webhookSignatureInvalid();
        }
    }

    private function api(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->tokenProvider->accessToken()])
            ->baseUrl(rtrim((string) $this->config['api_base_url'], '/'));
    }

    private function isTesting(): bool
    {
        return (bool) ($this->config['testing'] ?? false);
    }

    /** @param  list<string>  $keys */
    private function assertConfigured(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($this->config[$key])) {
                throw SignatureException::providerNotConfigured('zoho');
            }
        }
    }
}
