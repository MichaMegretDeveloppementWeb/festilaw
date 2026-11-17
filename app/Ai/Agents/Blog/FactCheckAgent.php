<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « verification » : relit l'article contre le dossier et retire toute affirmation que le dossier
 * n'etaye pas, sans rien ajouter. Ce qu'il retire ne va qu'au journal technique de l'etape.
 */
#[MaxTokens(9000)]
final class FactCheckAgent extends BlogAgent implements HasStructuredOutput
{
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
