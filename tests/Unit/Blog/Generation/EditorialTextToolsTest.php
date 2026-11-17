<?php

use App\Services\Blog\Generation\Editorial\BlogFactGuard;
use App\Services\Blog\Generation\Editorial\BlogTopicSimilarity;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;

/*
 | Outils de texte du pipeline editorial, sans Laravel : reperes du Markdown, garde des faits d'une
 | recriture, compteur de signes d'un texte genere, doublons de sujet.
 */

it('finds the internal link tokens and the image placeholders an agent wrote', function () {
    $markdown = "See [our pricing](festilaw:page/pricing) and [this guide](festilaw:post/12).\n\n[[image:1]]\n\nMore [pricing](festilaw:page/pricing).\n\n[[image:2]]";

    expect(EditorialMarkdown::linkTokens($markdown))->toBe(['festilaw:page/pricing', 'festilaw:post/12', 'festilaw:page/pricing'])
        ->and(EditorialMarkdown::imagePlaceholders($markdown))->toBe([1, 2]);
});

it('accepts a rewrite that keeps every fact, even when a translation writes numbers its own way', function () {
    $english = "Since 13 December 2024, 1,200 listings were hidden. Fines reach 4.5% of turnover. [Source](https://eur-lex.europa.eu/eli/reg/2023/988)\n\n[[image:1]]\n\n[Check](festilaw:page/quiz).";
    $french = "Depuis le 13 décembre 2024, 1\u{202F}200 annonces ont été masquées. Les amendes atteignent 4,5 % du chiffre d'affaires. [Source](https://eur-lex.europa.eu/eli/reg/2023/988)\n\n[[image:1]]\n\n[Vérifier](festilaw:page/quiz).";

    expect((new BlogFactGuard)->differences($english, $french))->toBe([]);
});

it('reports a rewrite that changed a number, a link, an internal link or an image', function () {
    $before = "Fines reach 4.5%. [EU](https://ec.europa.eu/a) [Quiz](festilaw:page/quiz)\n\n[[image:1]]";
    $after = 'Fines reach 5%. [EU](https://ec.europa.eu/b) [Pricing](festilaw:page/pricing)';

    expect((new BlogFactGuard)->differences($before, $after))->toBe([
        'numbers changed: missing [45], added [5]',
        'links changed: missing [https://ec.europa.eu/a], added [https://ec.europa.eu/b]',
        'link tokens changed: missing [festilaw:page/quiz], added [festilaw:page/pricing]',
        'image placeholders changed: missing [1], added []',
    ]);
});

it('counts the measurable signs of generated text, without blocking anything', function () {
    $markdown = <<<'MD'
## The GPSR: what changes

It's important to note that the rules are crucial — and they apply to every seller; this is not optional. Here is the point: labels matter: a lot.

Sellers must act fast. The deadline is near. That's it.

- A list item: with a colon.

[[image:1]]
MD;

    $signals = (new BlogWritingSignalsInspector)->inspect($markdown, ["it's important to note", 'crucial', 'delve']);

    expect($signals['dashes'])->toBe(1)
        ->and($signals['semicolons'])->toBe(1)
        ->and($signals['colon_paragraphs'])->toBe(1)
        ->and($signals['colons_in_headings'])->toBe(1)
        ->and($signals['phrases'])->toBe(["it's important to note" => 1, 'crucial' => 1])
        ->and($signals['short_endings'])->toBe(1)
        ->and($signals['sentences'])->toBe(5)
        ->and($signals['sentence_length_variation'])->toBeGreaterThan(0.0);
});

it('points at each sentence that carries a sign of generated text, with the name of the sign', function () {
    $markdown = "There are four things to do. What you can see is the status of your shop.\n\n"
        ."The pages reviewed here do not say more, so this guide stops. That is the wording, not a legal definition.\n\n"
        .'Etsy asks for the address of the person who makes the product.';
    $signs = [
        'matters',
        '~ \\bthere are (?:two|three|four) (?:things|steps)\\b => list announced by its count',
        '~ ^what\\s+(?:\\S+\\s+){1,5}is\\b => staged "what X is"',
        '~ \\b(?:pages?|sources?)\\s+reviewed\\s+here\\b => remark on the sources',
        '~ \\b(?:this|our) (?:guide|article)\\b => remark on the sources',
        '~ ,\\s+not\\s+(?:a|an|the)\\b => X, not Y',
    ];

    $signals = (new BlogWritingSignalsInspector)->inspect($markdown, $signs);

    // Deux motifs du meme nom comptent tous les deux, mais une phrase ne porte un nom qu'une fois.
    expect($signals['flagged'])->toBe([
        ['signs' => ['list announced by its count'], 'sentence' => 'There are four things to do.'],
        ['signs' => ['staged "what X is"'], 'sentence' => 'What you can see is the status of your shop.'],
        ['signs' => ['remark on the sources'], 'sentence' => 'The pages reviewed here do not say more, so this guide stops.'],
        ['signs' => ['X, not Y'], 'sentence' => 'That is the wording, not a legal definition.'],
    ])->and($signals['patterns'])->toBe(['list announced by its count' => 1, 'staged "what X is"' => 1, 'remark on the sources' => 1, 'X, not Y' => 1]);
});

it('accepts the semicolon that ends a list item in French, and still flags one that joins two clauses', function () {
    $markdown = "L'offre doit indiquer :\n\n- le nom du fabricant ;\n- l'adresse de la personne responsable.\n\nLa fiche est complète ; le vendeur peut publier.";

    $signals = (new BlogWritingSignalsInspector)->inspect($markdown, []);

    // Typographie francaise des listes, demandee le 17/11 apres le premier article reel.
    expect($signals['flagged'])->toBe([
        ['signs' => ['semicolon joining clauses'], 'sentence' => 'La fiche est complète ; le vendeur peut publier.'],
    ]);
});

it('sees no sign in plain, varied prose', function () {
    $markdown = "Etsy asks every seller who ships to the EU to name an economic operator in the listing settings.\n\nIf you sell from Leeds, that person must be established in the Union, and the platform hides listings that lack one.";

    $signals = (new BlogWritingSignalsInspector)->inspect($markdown, ['crucial']);

    expect($signals['dashes'] + $signals['semicolons'] + $signals['colon_paragraphs'] + $signals['short_endings'])->toBe(0)
        ->and($signals['phrases'])->toBe([])
        ->and($signals['flagged'])->toBe([]);
});

it('spots a topic that repeats a known title, whatever its case, accents or small words', function (string $candidate, ?string $expected) {
    $known = ['Adding an economic operator on Etsy', 'Étiquette produit : la liste à vérifier', 'Bougies artisanales et marché européen'];

    expect((new BlogTopicSimilarity)->duplicateOf($candidate, $known))->toBe($expected);
})->with([
    'same words, other order' => ['How to add your economic operator on Etsy', 'Adding an economic operator on Etsy'],
    'accents and case' => ['ETIQUETTE PRODUIT, LA LISTE A VERIFIER', 'Étiquette produit : la liste à vérifier'],
    'a new subject' => ['Toy safety rules for wooden toys sold on Amazon', null],
    'a neighbour, left to the model' => ['Candle warning labels for EU buyers', null],
]);
