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
 *
 * Limite de 16 000 jetons, reflexion comprise : DeepSeek V4.1 Flash ignore l'effort « low » et a reflechi
 * jusqu'a 9 000 jetons le 17/11 (generation 77), ce qui coupait la reponse. Decision de Micha du 17/11 :
 * garder la reflexion et relever la limite.
 */
#[MaxTokens(16000)]
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
