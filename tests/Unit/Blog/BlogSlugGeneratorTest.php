<?php

use App\Services\Blog\Slug\BlogSlugGenerator;

it('produces the reference slugs of the specification', function (string $title, string $expected) {
    expect((new BlogSlugGenerator)->fromText($title))->toBe($expected);
})->with([
    'french apostrophes and colon' => ['L\'étiquetage GPSR : ce qu\'il faut savoir', 'l-etiquetage-gpsr-ce-qu-il-faut-savoir'],
    'spanish opening marks' => ['¿Qué es una persona responsable en la UE?', 'que-es-una-persona-responsable-en-la-ue'],
    'ampersand and comma' => ['Etsy, Amazon & eBay : 5 erreurs à éviter', 'etsy-amazon-ebay-5-erreurs-a-eviter'],
    'english apostrophe' => ['Selling to the EU after Brexit: what\'s changed?', 'selling-to-the-eu-after-brexit-what-s-changed'],
]);

it('normalises typographic characters, repeated separators and edges', function (string $text, string $expected) {
    expect((new BlogSlugGenerator)->fromText($text))->toBe($expected);
})->with([
    'typographic apostrophe' => ['L’étiquette', 'l-etiquette'],
    'spanish exclamation and tilde' => ['¡Año nuevo, normas nuevas!', 'ano-nuevo-normas-nuevas'],
    'cedilla and ligature' => ['Reçu : le cœur du dossier', 'recu-le-coeur-du-dossier'],
    'repeated separators' => ['  GPSR --- 2026 !!  ', 'gpsr-2026'],
    'already a slug' => ['mon-article-2', 'mon-article-2'],
    'upper case typed slug' => ['Mon Article', 'mon-article'],
    'nothing usable' => ['¿¡ !? …', ''],
    'empty' => ['', ''],
]);

it('cuts a long slug between two words, never above the maximum length', function () {
    $generator = new BlogSlugGenerator(maxLength: 30);

    $slug = $generator->fromText('Understanding the responsible person obligations in the European Union');

    expect($slug)->toBe('understanding-the-responsible')
        ->and(strlen($slug))->toBeLessThanOrEqual(30);
});

it('keeps the whole word when the limit falls exactly at its end', function () {
    expect((new BlogSlugGenerator(maxLength: 9))->fromText('four five six'))->toBe('four-five');
});

it('hard cuts a single word longer than the limit', function () {
    expect((new BlogSlugGenerator(maxLength: 5))->fromText('responsibility'))->toBe('respo');
});

it('returns the base slug when it is free and numbers it otherwise', function () {
    $generator = new BlogSlugGenerator;
    $taken = ['gpsr-guide', 'gpsr-guide-2'];

    expect($generator->firstAvailable('label-rules', fn (string $slug): bool => in_array($slug, $taken, true)))->toBe('label-rules')
        ->and($generator->firstAvailable('gpsr-guide', fn (string $slug): bool => in_array($slug, $taken, true)))->toBe('gpsr-guide-3');
});

it('shortens the base so that a numbered slug still fits the maximum length', function () {
    $generator = new BlogSlugGenerator(maxLength: 12);

    $slug = $generator->firstAvailable('alpha-beta', fn (string $slug): bool => $slug === 'alpha-beta');

    expect($slug)->toBe('alpha-beta-2')->and(strlen($slug))->toBeLessThanOrEqual(12);

    $shortened = $generator->firstAvailable('alpha-bravo', fn (string $slug): bool => $slug === 'alpha-bravo');

    expect($shortened)->toBe('alpha-2');
});
