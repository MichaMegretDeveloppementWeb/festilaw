<?php

use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\GenerationLength;

/*
 | Regles de longueur du texte d'un article : bornes du reglage (400 a 2 000 mots, decision du 17/11), ecart
 | demande au modele (10 %) et ecart accepte avant une demande de correction (20 %). Calcul pur.
 */

function lengthRules(): ArticleLengthRules
{
    return new ArticleLengthRules(minWords: 400, maxWords: 2_000, askedTolerancePercent: 10, acceptedTolerancePercent: 20);
}

it('keeps a length within the bounds of the setting, on the nearest step', function (int $asked, int $kept) {
    expect(lengthRules()->clamp($asked))->toBe($kept);
})->with([
    'too short' => [100, 400],
    'within' => [1_000, 1_000],
    'too long' => [9_000, 2_000],
    // Une longueur enregistree avant les paliers du 18/11 revient au palier le plus proche.
    'between two steps' => [950, 1_000],
    'just above a step' => [1_250, 1_200],
]);

it('offers the lengths by steps of 200 words, from 400 to 2 000', function () {
    expect(lengthRules()->options())->toBe([400, 600, 800, 1_000, 1_200, 1_400, 1_600, 1_800, 2_000]);
});

it('works out the range asked to the model and the wider range the writing step accepts', function () {
    $length = lengthRules()->for(1_000);

    expect([$length->words, $length->askedMin, $length->askedMax, $length->acceptedMin, $length->acceptedMax])->toBe([1_000, 900, 1_100, 800, 1_200])
        ->and($length->accepts(800))->toBeTrue()
        ->and($length->accepts(1_200))->toBeTrue()
        ->and($length->accepts(522))->toBeFalse()
        ->and($length->accepts(1_201))->toBeFalse()
        ->and($length->toPayload())->toBe(['words' => 1_000]);
});

it('reads the length written on a generation, or nothing for an older one', function () {
    expect(GenerationLength::wordsFromPayload(['length' => ['words' => 1_200]]))->toBe(1_200)
        ->and(GenerationLength::wordsFromPayload(['images' => ['mode' => 'stock', 'max' => 1]]))->toBeNull()
        ->and(GenerationLength::wordsFromPayload(null))->toBeNull();
});
