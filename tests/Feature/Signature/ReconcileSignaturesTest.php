<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Data\Signature\SignatureWebhookData;
use App\Enums\Contract\SignatureEventOutcome;
use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('signature.default', 'signwell');
    // Gateway bouchonne (aucun appel HTTP) : checkStatus non concluant -> le contrat est verifie mais
    // rien n'est regle. Les tests qui doivent forcer une issue rebindent l'interface.
    $gateway = Mockery::mock(SignatureGatewayInterface::class);
    $gateway->shouldReceive('key')->andReturn('signwell');
    $gateway->shouldReceive('checkStatus')->andReturnUsing(
        fn (Contract $contract) => new SignatureWebhookData(
            providerReference: (string) $contract->signature_provider_reference,
            outcome: SignatureEventOutcome::Unresolved,
        ),
    );
    $gateway->shouldReceive('downloadSignedDocument')->andReturnNull();
    app()->instance(SignatureGatewayInterface::class, $gateway);
});

/** A pending contract with the given overrides, attached to a fresh dossier. */
function pendingContract(array $attrs = []): Contract
{
    $createdAt = $attrs['created_at'] ?? now()->subHour();
    unset($attrs['created_at']);

    $contract = Contract::factory()->for(Submission::factory()->starter())->create(array_merge([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'doc_'.fake()->uuid(),
    ], $attrs));

    $contract->created_at = $createdAt;
    $contract->save();

    return $contract;
}

it('checks only stale pending contracts with a provider reference', function () {
    pendingContract(); // éligible
    pendingContract(['created_at' => now()]); // trop récent → ignoré
    pendingContract(['signature_provider_reference' => null]); // sans référence → ignoré
    pendingContract(['signature_status' => SignatureStatus::Signed]); // déjà signé → ignoré

    $this->artisan('festilaw:reconcile-signatures')
        ->expectsOutputToContain('verifies : 1')
        ->assertOk();
});

it('dry run writes nothing', function () {
    pendingContract();

    $this->artisan('festilaw:reconcile-signatures', ['--dry' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertOk();

    expect(Contract::where('signature_status', SignatureStatus::Signed)->count())->toBe(0);
});
