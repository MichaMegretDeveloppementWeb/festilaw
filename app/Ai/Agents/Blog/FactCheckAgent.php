<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « verification » : relit l'article contre le dossier et retire toute affirmation que le dossier
 * n'etaye pas, sans rien ajouter. Ce qu'il retire ne va qu'au journal technique de l'etape.
 */
final class FactCheckAgent extends BlogAgent implements HasStructuredOutput
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
            'removed_claims' => $schema->array()->items($schema->object([
                'claim' => $schema->string()->required(),
                'reason' => $schema->string()->required(),
            ]))->required(),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
