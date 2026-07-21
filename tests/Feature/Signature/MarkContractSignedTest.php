<?php

use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => true,
    ]);
    app()->forgetInstance(SignatureGatewayInterface::class);
    Storage::fake('local');
});

/** A contract in the given signature status, tied to a SignWell document. */
function signwellContract(SignatureStatus $status, array $attrs = []): Contract
{
    return Contract::factory()->for(Submission::factory()->starter()->create(['status' => SubmissionStatus::InProgress]))
        ->create(array_merge([
            'signature_status' => $status,
            'signature_provider' => 'signwell',
            'signature_provider_reference' => 'DOC1',
        ], $attrs));
}

it('downloads the signed PDF once on the real Pending -> Signed transition', function () {
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF-BYTES', 200)]);

    $contract = signwellContract(SignatureStatus::Pending);

    app(MarkContractSignedAction::class)->execute($contract, 'DOC1');

    expect($contract->fresh()->signature_status)->toBe(SignatureStatus::Signed)
        ->and($contract->fresh()->signed_file_path)->toBe('contracts/DOC1.pdf')
        ->and($contract->submission->fresh()->status)->toBe(SubmissionStatus::AwaitingDocuments);
    Storage::disk('local')->assertExists('contracts/DOC1.pdf');
    Http::assertSent(fn ($req) => str_contains($req->url(), 'completed_pdf'));
});

it('never re-downloads the signed PDF on a replayed webhook (already signed)', function () {
    Http::fake(['*/api/v1/documents/*/completed_pdf*' => Http::response('SIGNED-PDF-BYTES', 200)]);

    $contract = signwellContract(SignatureStatus::Signed, [
        'signed_file_path' => 'contracts/DOC1.pdf',
        'signed_at' => now(),
    ]);

    app(MarkContractSignedAction::class)->execute($contract, 'DOC1');

    // Le pre-check (etat non-confirmable) coupe avant tout appel : aucun re-telechargement.
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'completed_pdf'));
});
