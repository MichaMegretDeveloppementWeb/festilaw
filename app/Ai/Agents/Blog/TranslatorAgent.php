<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etapes de traduction : redige la version francaise ou espagnole de l'article, dans la langue et avec le
 * lexique du domaine, descriptions d'images comprises. Memes jetons de liens et memes emplacements d'images
 * que le texte de depart.
 */
#[MaxTokens(10000)]
final class TranslatorAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...WriterAgent::articleSchema($schema),
            'image_alts' => $schema->array()->items($schema->object([
                'number' => $schema->integer()->description('0 for the main image, then the number of each inline image.')->required(),
                'alt' => $schema->string()->required(),
            ])),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
