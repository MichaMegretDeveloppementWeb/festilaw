<?php

declare(strict_types=1);

use App\Enums\Contract\ContractRole;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\SubmissionDetail;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Contract\ContractPdfGenerator;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Changement de pack apres paiement (SC12), fondations : un dossier garde l'historique de ses mandats. Un seul
 | est en vigueur (lu partout), celui d'un changement en cours attend, l'ancien est conserve comme trace.
 */

uses(RefreshDatabase::class);

/** Un client Creator passe au Pro : son mandat Creator remplace, son mandat Pro en vigueur. */
function upgradedDossier(): Submission
{
    $dossier = Submission::factory()->pro()->paid()->create(['resume_token' => 'histo', 'locale' => 'en']);
    $dossier->contract->update([
        'pack' => SubmissionType::Pro,
        'signed_file_path' => 'contracts/pro.pdf',
    ]);
    $dossier->contracts()->create([
        'pack' => SubmissionType::Starter,
        'role' => ContractRole::Superseded,
        'superseded_at' => now(),
        'signature_status' => SignatureStatus::Signed,
        'signed_at' => now()->subYear(),
        'signed_file_path' => 'contracts/creator.pdf',
        'countersigned_file_path' => 'contracts/countersigned/contract-old.pdf',
    ]);

    return $dossier->fresh();
}

it('reads only the mandate in force, and keeps the previous one in the history', function () {
    $dossier = upgradedDossier();

    expect($dossier->contract->pack)->toBe(SubmissionType::Pro)
        ->and($dossier->contracts)->toHaveCount(2)
        ->and(app(StarterDossierResolver::class)->resolve($dossier)->contractSigned)->toBeTrue();
});

it('renders a mandate with its own pack and fee, whatever the pack of the dossier', function () {
    $creator = Submission::factory()->starter()->paid()->create(['locale' => 'en']);
    $pendingPro = $creator->contracts()->create([
        'pack' => SubmissionType::Pro,
        'role' => ContractRole::Pending,
        'signature_status' => SignatureStatus::Pending,
        'filled_fields' => [],
    ]);
    $generator = app(ContractPdfGenerator::class);

    $proHtml = view('contracts.en.agreement', $generator->agreementData($creator, $pendingPro))->render();
    $currentHtml = view('contracts.en.agreement', $generator->agreementData($creator))->render();

    expect($proHtml)->toContain('Pack Pro')->toContain('EUR 1200')
        ->and($currentHtml)->toContain('Pack Creator')->toContain('EUR 333');
});

it('erases the files of every mandate of a deleted dossier (GDPR)', function () {
    Storage::fake('local');
    $dossier = upgradedDossier();
    foreach (['contracts/pro.pdf', 'contracts/creator.pdf', 'contracts/countersigned/contract-old.pdf'] as $path) {
        Storage::disk('local')->put($path, '%PDF');
    }

    $dossier->delete();

    foreach (['contracts/pro.pdf', 'contracts/creator.pdf', 'contracts/countersigned/contract-old.pdf'] as $path) {
        Storage::disk('local')->assertMissing($path);
    }
});

it('lists the previous mandates in the back-office and lets the admin download them', function () {
    Storage::fake('local');
    Storage::disk('local')->put('contracts/creator.pdf', '%PDF creator');
    $dossier = upgradedDossier();
    $previous = $dossier->contracts->firstWhere('role', ContractRole::Superseded);
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier])
        ->assertSee('Pack du mandat')
        ->assertSee('Mandats précédents')
        ->assertSee('Mandat Creator Pack');

    get(route('admin.submissions.contract-mandate', ['submission' => $dossier->id, 'contract' => $previous->id]))
        ->assertOk()
        ->assertDownload('festilaw-mandate-'.$dossier->reference.'-creator.pdf');

    // Un mandat d'un autre dossier n'est pas accessible par cette route.
    $other = Contract::factory()->signed()->create(['signed_file_path' => 'contracts/creator.pdf']);
    get(route('admin.submissions.contract-mandate', ['submission' => $dossier->id, 'contract' => $other->id]))->assertNotFound();
});

it('reports the annual fee, not a pack upgrade, as the payment of the project', function () {
    $dossier = Submission::factory()->starter()->paid()->create(['resume_token' => 'lastpay', 'locale' => 'en']);
    Payment::factory()->succeeded()->for($dossier)->create([
        'type' => PaymentType::PackUpgrade,
        'amount_cents' => 21675,
        'paid_at' => now()->addMinute(),
    ]);

    get(route('my-project', ['dossier' => 'lastpay']))
        ->assertOk()
        ->assertSee('€333')
        ->assertDontSee('€216.75');
});
