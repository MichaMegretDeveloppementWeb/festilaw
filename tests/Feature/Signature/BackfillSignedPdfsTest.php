<?php

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use App\Models\Submission;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Rattrapage du PDF signe (chaque minute) : avec la signature integree, le navigateur confirme la signature
 | avant que le prestataire ait genere le PDF final. Le contrat est "signe" sans fichier local jusqu'ici.
 */

uses(RefreshDatabase::class);

/** Binds a stub SignWell gateway whose signed-PDF download returns $path (null = not ready yet). */
function bindBackfillGateway(?string $path): void
{
    $gateway = Mockery::mock(SignatureGatewayInterface::class);
    $gateway->shouldReceive('key')->andReturn('signwell');
    $gateway->shouldReceive('downloadSignedDocument')->andReturn($path);
    app()->instance(SignatureGatewayInterface::class, $gateway);
}

/** A signed contract whose signed PDF was never downloaded. */
function signedContractWithoutPdf(): Contract
{
    return Contract::factory()->for(Submission::factory()->starter())->create([
        'signature_status' => SignatureStatus::Signed,
        'signature_provider' => 'signwell',
        'signature_provider_reference' => 'doc_'.fake()->uuid(),
        'signed_file_path' => null,
        'signed_at' => now(),
    ]);
}

it('backfills the signed PDF of a signed contract whose file is still missing', function () {
    bindBackfillGateway('contracts/backfilled.pdf');
    $contract = signedContractWithoutPdf();

    $this->artisan('festilaw:backfill-signed-pdfs')
        ->expectsOutputToContain('rattrapes : 1')
        ->assertOk();

    expect($contract->fresh()->signed_file_path)->toBe('contracts/backfilled.pdf');
});

it('leaves the contract untouched while the provider has not generated the PDF yet', function () {
    bindBackfillGateway(null);
    $contract = signedContractWithoutPdf();

    $this->artisan('festilaw:backfill-signed-pdfs')
        ->expectsOutputToContain('manquants : 1 · rattrapes : 0')
        ->assertOk();

    expect($contract->fresh()->signed_file_path)->toBeNull()
        ->and($contract->fresh()->signature_status)->toBe(SignatureStatus::Signed);
});

it('counts without downloading anything on a dry run', function () {
    $gateway = Mockery::mock(SignatureGatewayInterface::class);
    $gateway->shouldReceive('key')->andReturn('signwell');
    $gateway->shouldNotReceive('downloadSignedDocument');
    app()->instance(SignatureGatewayInterface::class, $gateway);
    signedContractWithoutPdf();

    $this->artisan('festilaw:backfill-signed-pdfs', ['--dry' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertOk();
});

it('runs every minute, the status reconciliation every five minutes', function () {
    $events = collect(app(Schedule::class)->events());
    $expression = fn (string $command): ?string => $events
        ->first(fn (Event $event): bool => str_contains((string) $event->command, $command))?->expression;

    expect($expression('festilaw:backfill-signed-pdfs'))->toBe('* * * * *')
        ->and($expression('festilaw:reconcile-signatures'))->toBe('*/5 * * * *');
});
