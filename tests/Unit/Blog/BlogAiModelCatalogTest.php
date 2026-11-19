<?php

use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Services\Blog\Generation\BlogAiModelCatalog;

/*
 | Calculs purs des reglages de la generation par IA : la liste fermee des modeles (texte et images), la
 | liste de sujets, et les images propres a une generation.
 */

function blogAiCatalog(): BlogAiModelCatalog
{
    return new BlogAiModelCatalog(
        [
            'anthropic/claude-sonnet-5.5' => ['name' => 'Claude Sonnet 5.5', 'provider' => 'Anthropic', 'input' => 200, 'output' => 1_000],
            'anthropic/claude-haiku-5.5' => ['name' => 'Claude Haiku 5.5', 'provider' => 'Anthropic', 'input' => 10, 'output' => 50],
            'google/gemini-4-hydrogen' => ['name' => 'Gemini 4 Hydrogen', 'provider' => 'Google', 'input' => 150, 'output' => 750],
            'bare/model' => [],
        ],
        ['google/gemini-3.1-flash-image' => ['name' => 'Nano Banana 2', 'provider' => 'Google']],
    );
}

it('offers the text models and the image models as two closed lists', function () {
    $catalog = blogAiCatalog();

    expect($catalog->idsFor('writing'))->toBe(['anthropic/claude-sonnet-5.5', 'anthropic/claude-haiku-5.5', 'google/gemini-4-hydrogen', 'bare/model'])
        ->and($catalog->idsFor('topic'))->toBe($catalog->idsFor('writing'))
        // L'etape des images ne propose que des modeles qui generent des images.
        ->and($catalog->idsFor(BlogAiModelCatalog::IMAGE_SETTING))->toBe(['google/gemini-3.1-flash-image']);
});

it('labels a model with its commercial name, its publisher and its indicative price', function () {
    $catalog = blogAiCatalog();

    expect($catalog->optionsFor('writing'))->toBe([
        'anthropic/claude-sonnet-5.5' => 'Claude Sonnet 5.5 (Anthropic) · 2 $ / 10 $',
        'anthropic/claude-haiku-5.5' => 'Claude Haiku 5.5 (Anthropic) · 0,10 $ / 0,50 $',
        'google/gemini-4-hydrogen' => 'Gemini 4 Hydrogen (Google) · 1,50 $ / 7,50 $',
        // Un modele sans nom ni prix reste proposable, sous son identifiant.
        'bare/model' => 'bare/model',
    ])->and($catalog->optionsFor(BlogAiModelCatalog::IMAGE_SETTING))->toBe([
        // Le prix d'une image n'a pas ete releve : aucun prix n'est affiche.
        'google/gemini-3.1-flash-image' => 'Nano Banana 2 (Google)',
    ]);
});

it('names a model by its commercial name, and by its identifier once it left the list', function () {
    $catalog = blogAiCatalog();

    expect($catalog->name('anthropic/claude-sonnet-5.5'))->toBe('Claude Sonnet 5.5')
        ->and($catalog->name('google/gemini-3.1-flash-image'))->toBe('Nano Banana 2')
        ->and($catalog->name('old/retired-model'))->toBe('old/retired-model')
        ->and($catalog->name(null))->toBeNull()
        ->and($catalog->name(''))->toBeNull();
});

it('reads the images of a generation from what was written on it', function () {
    $options = GenerationImageOptions::fromPayload(['images' => ['mode' => 'generated', 'max' => 2], 'other' => true]);

    expect($options?->mode)->toBe(BlogAiImageMode::Generated)
        ->and($options?->maxImages)->toBe(2)
        ->and($options?->toPayload())->toBe(['mode' => 'generated', 'max' => 2])
        // Un nombre hors bornes est ramene entre 1 et 3.
        ->and(GenerationImageOptions::fromPayload(['images' => ['mode' => 'stock', 'max' => 9]])?->maxImages)->toBe(BlogAiSettings::MAX_IMAGES)
        ->and(GenerationImageOptions::fromPayload(['images' => ['mode' => 'none']])?->maxImages)->toBe(1)
        // Une generation anterieure a ce choix, ou une valeur inconnue, ne pretend rien.
        ->and(GenerationImageOptions::fromPayload(null))->toBeNull()
        ->and(GenerationImageOptions::fromPayload([]))->toBeNull()
        ->and(GenerationImageOptions::fromPayload(['images' => ['mode' => 'hologram', 'max' => 2]]))->toBeNull();
});
