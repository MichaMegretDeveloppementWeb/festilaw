<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

/*
 | festilaw:contract-specimens : les 6 modeles vierges du mandat (Creator et Pro, EN/FR/ES) a transmettre a
 | Festilaw, sur le disque local prive.
 */

it('writes a blank PDF specimen of the mandate for each pack and language', function () {
    $disk = Storage::fake('local');

    $this->artisan('festilaw:contract-specimens')
        ->expectsOutputToContain('festilaw-contract-creator-fr.pdf')
        ->expectsOutputToContain('Modeles du mandat generes (6 PDF).')
        ->assertOk();

    $files = $disk->files('contract-specimens');
    sort($files);

    expect($files)->toBe([
        'contract-specimens/festilaw-contract-creator-en.pdf',
        'contract-specimens/festilaw-contract-creator-es.pdf',
        'contract-specimens/festilaw-contract-creator-fr.pdf',
        'contract-specimens/festilaw-contract-pro-en.pdf',
        'contract-specimens/festilaw-contract-pro-es.pdf',
        'contract-specimens/festilaw-contract-pro-fr.pdf',
    ]);

    foreach ($files as $file) {
        expect($disk->get($file))->toStartWith('%PDF');
    }
});
