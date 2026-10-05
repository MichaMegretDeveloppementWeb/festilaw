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
        'locale' => 'en',
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

it('generates the agreement in each supported language', function (string $locale) {
    $submission = Submission::factory()->create([
        'type' => SubmissionType::Pro,
        'locale' => $locale,
        'company_name' => 'Beta Corp',
    ]);
    $submission->contract()->create([
        'signature_status' => SignatureStatus::Pending,
        'filled_fields' => [],
    ]);

    $pdf = app(ContractPdfGenerator::class)->generate($submission->fresh());

    expect($pdf)->toStartWith('%PDF');
})->with(['en', 'fr', 'es']);

it('injects the pack, fee and emphasised client fields, includes the annex, and carries the signature text tag', function () {
    $html = view('contracts.en.agreement', [
        'logo' => '',
        'pack' => 'Creator',
        'fee' => 333,
        'feeWords' => 'three hundred and thirty-three euros',
        'date' => '20 July 2026',
        'reference' => 'FL-TEST-0001',
        'company' => '<strong><em>Acme Trading Ltd</em></strong>',
        'place' => '<strong><em>Toronto, Canada</em></strong>',
        'year' => '<strong><em>2015</em></strong>',
        'activity' => '<strong><em>the sale of home goods</em></strong>',
        'signer' => 'Maya Chen',
    ])->render();

    expect($html)
        ->toContain('Pack Creator')
        ->toContain('EUR 333')
        ->toContain('three hundred and thirty-three euros')
        ->toContain('333 € annual fee')
        ->toContain('Governing Law and Jurisdiction')
        // Client-provided fields are rendered bold + italic.
        ->toContain('<strong><em>Acme Trading Ltd</em></strong>')
        ->toContain('<strong><em>the sale of home goods</em></strong>')
        // Invisible SignWell text tags for the signature + date fields.
        ->toContain('{{signature:1:y}}')
        ->toContain('{{date:1:y}}');
});

it('submits any dispute to the Rechtbank Gelderland in every language (Festilaw, 04/10/2026)', function (string $locale, string $clause) {
    $html = view("contracts.{$locale}.general-terms", ['fee' => 333])->render();

    expect($html)->toContain($clause)
        ->not->toContain('Brabant')
        ->not->toContain('Hertogenbosch');
})->with([
    'en' => ['en', 'Any dispute shall be submitted to the exclusive jurisdiction of the Rechtbank Gelderland.'],
    'fr' => ['fr', 'Tout litige sera soumis à la compétence exclusive de la Rechtbank Gelderland.'], // formule de Festilaw
    'es' => ['es', 'Todo litigio se someterá a la competencia exclusiva del Rechtbank Gelderland.'],
]);

it('renders a blank specimen of each pack in each language', function (SubmissionType $type, string $locale) {
    expect(app(ContractPdfGenerator::class)->specimen($type, $locale))->toStartWith('%PDF');
})->with([SubmissionType::Starter, SubmissionType::Pro])->with(['en', 'fr', 'es']);

it('fills a specimen with bracketed placeholders instead of client details', function () {
    $generator = app(ContractPdfGenerator::class);

    $french = view('contracts.fr.agreement', $generator->specimenData(SubmissionType::Pro, 'fr'))->render();
    expect($french)
        ->toContain('MODÈLE')
        ->toContain('<strong><em>[Nom de la société cliente]</em></strong>')
        ->toContain('<strong><em>[ville, pays]</em></strong>')
        ->toContain('[Nom du signataire]')
        ->toContain('mille deux cents euros')
        ->toContain('Rechtbank Gelderland');

    $english = view('contracts.en.agreement', $generator->specimenData(SubmissionType::Starter, 'en'))->render();
    expect($english)
        ->toContain('SPECIMEN')
        ->toContain('<strong><em>[Client company name]</em></strong>')
        ->toContain('Pack Creator')
        ->toContain('three hundred and thirty-three euros');
});
