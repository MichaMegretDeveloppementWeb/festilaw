<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Le gateway de signature est resolu a la construction : on force la reprise sur 'fake'.
    config()->set('signature.default', 'fake');
    app()->forgetInstance(SignatureGatewayInterface::class);
});

/** A pending contract with the given overrides, attached to a fresh dossier. */
function pendingContract(array $attrs = []): Contract
{
    $createdAt = $attrs['created_at'] ?? now()->subHour();
    unset($attrs['created_at']);

    $contract = Contract::factory()->for(Submission::factory()->starter())->create(array_merge([
        'signature_status' => SignatureStatus::Pending,
        'signature_provider' => 'fake',
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
