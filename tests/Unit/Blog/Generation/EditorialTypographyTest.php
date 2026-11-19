<?php

use App\Data\Blog\Generation\EditorialArticle;
use App\Services\Blog\Generation\Editorial\EditorialTypography;

/*
 | Typographie de la langue posee en code sur les versions francaise et espagnole (relecture du brouillon 99
 | par Micha, 18/11) : la ponctuation anglaise trahissait une traduction.
 */

it('sets French punctuation, quotation marks and numbers the way French readers expect', function () {
    $typed = (new EditorialTypography)->apply('Le champ "economic operator" : obligatoire ; vraiment ? Oui ! 4 % des 1 200 vendeurs.', 'fr');

    expect($typed)->toBe("Le champ «\u{00A0}economic operator\u{00A0}»\u{00A0}: obligatoire\u{202F}; vraiment\u{202F}? Oui\u{202F}! 4\u{00A0}% des 1\u{00A0}200 vendeurs.");
});

it('never touches a link address, an internal link token, an image placeholder or a time', function () {
    $text = "Voir [le règlement](https://eur-lex.europa.eu/eli/reg/2023/988/oj?lang=fr) et [le quiz](festilaw:page/quiz).\n\n[[image:1]]\n\nÀ côté de l'image.\n\n[[/image:1]]\n\nRendez-vous à 10:30.";

    expect((new EditorialTypography)->apply($text, 'fr'))->toBe($text);
});

it('changes nothing the second time', function () {
    $typography = new EditorialTypography;
    $once = $typography->apply('Le champ "economic operator" : obligatoire ; vraiment ? 4 % et 1 200.', 'fr');

    expect($typography->apply($once, 'fr'))->toBe($once);
});

it('writes Spanish angular quotation marks against the words, and leaves English alone', function () {
    $typography = new EditorialTypography;

    expect($typography->apply('La etiqueta "economic operator" y « otra ».', 'es'))->toBe('La etiqueta «economic operator» y «otra».')
        ->and($typography->apply('The "economic operator" field: required; really?', 'en'))->toBe('The "economic operator" field: required; really?');
});

it('sets every text field of an article', function () {
    $article = (new EditorialTypography)->article(new EditorialArticle('Un titre ?', 'Un extrait : court.', 'Titre ?', 'Description ; courte.', 'Le corps !'), 'fr');

    expect($article->title)->toBe("Un titre\u{202F}?")
        ->and($article->excerpt)->toBe("Un extrait\u{00A0}: court.")
        ->and($article->metaTitle)->toBe("Titre\u{202F}?")
        ->and($article->metaDescription)->toBe("Description\u{202F}; courte.")
        ->and($article->body)->toBe("Le corps\u{202F}!");
});
