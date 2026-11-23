<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etapes de traduction : redige la version francaise ou espagnole de l'article, dans la langue et avec le
 * lexique du domaine, descriptions d'images comprises. Memes jetons de liens et memes emplacements d'images
 * que le texte de depart.
 */
final class TranslatorAgent extends BlogAgent implements HasStructuredOutput
{
    public function __construct(string $instructions = '', private readonly ?int $answerWords = null)
    {
        parent::__construct($instructions);
    }

    public function maxTokens(): int
    {
        return self::tokensForArticle($this->answerWords);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...WriterAgent::articleSchema($schema),
            'image_alts' => $schema->array()->items($schema->object([
                'number' => $schema->integer()->description('0 for the main image, then the number of each inline image.')->required(),
                'alt' => $schema->string()->required(),
            ])),
            'category_name' => $schema->string()->description('The name of the new category given in the request, in the target language. Empty when the request gives none.')->nullable(),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
