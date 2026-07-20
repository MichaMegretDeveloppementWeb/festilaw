<?php

declare(strict_types=1);

use App\Enums\Contract\SignatureStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use App\Services\Contract\ContractPdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a Creator agreement PDF', function () {
    $submission = Submission::factory()->create([
        'type' => SubmissionType::Starter,
        'company_name' => 'Acme Trading Ltd',
    ]);
    $submission->contract()->create([
        'signature_status' => SignatureStatus::Pending,
        'filled_fields' => [
            'incorporation_place' => 'Toronto, Canada',
            'founding_year' => '2015',
            'activity' => 'the design and sale of home goods',
        ],
    ]);

    $pdf = app(ContractPdfGenerator::class)->generate($submission->fresh());

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(8000);
});

it('generates a Pro agreement PDF even without filled fields', function () {
    $submission = Submission::factory()->create([
        'type' => SubmissionType::Pro,
        'company_name' => 'Beta Corp',
    ]);
    $submission->contract()->create([
        'signature_status' => SignatureStatus::Pending,
        'filled_fields' => [],
    ]);

    $pdf = app(ContractPdfGenerator::class)->generate($submission->fresh());

    expect($pdf)->toStartWith('%PDF');
});

it('injects pack, fee and client fields, and includes the General Terms annex', function () {
    $html = view('contracts.responsible-person-agreement', [
        'submission' => Submission::factory()->make(['reference' => 'FL-TEST-0001']),
        'date' => '01 January 2026',
        'pack' => 'Creator',
        'fee' => 333,
        'feeWords' => 'three hundred and thirty-three euros',
        'company' => 'Acme Trading Ltd',
        'place' => 'Toronto, Canada',
        'year' => '2015',
        'activity' => 'the sale of home goods',
    ])->render();

    expect($html)
        ->toContain('Acme Trading Ltd')
        ->toContain('Pack Creator')
        ->toContain('EUR 333')
        ->toContain('three hundred and thirty-three euros')
        ->toContain('the sale of home goods')
        ->toContain('Toronto, Canada')
        ->toContain('333 € annual fee')
        ->toContain('Governing Law and Jurisdiction');
});
