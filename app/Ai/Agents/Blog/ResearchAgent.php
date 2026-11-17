<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Etape « recherche » : constitue le dossier de faits sources du sujet (affirmation, source, date, type de
 * source). C'est le seul agent qui lit des pages : ce qu'il rend n'est qu'un dossier, que le redacteur, sans
 * outil, utilise ensuite.
 */
#[MaxSteps(12)]
#[MaxTokens(6000)]
final class ResearchAgent extends BlogAgent implements HasStructuredOutput, HasTools
{
    public function tools(): iterable
    {
        return [(new WebSearch)->max(8), (new WebFetch)->max(6)];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sources' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('S1, S2...')->required(),
                'title' => $schema->string()->required(),
                'url' => $schema->string()->required(),
                'publisher' => $schema->string()->required(),
                'type' => $schema->string()->enum(['official', 'professional', 'forum'])->required(),
                'date' => $schema->string()->description('Publication or update date, YYYY-MM-DD, when known.')->nullable(),
            ]))->required(),
            'facts' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('F1, F2...')->required(),
                'claim' => $schema->string()->required(),
                'source_ids' => $schema->array()->items($schema->string())->required(),
                'date' => $schema->string()->nullable(),
            ]))->required(),
            'notes' => $schema->string()->nullable(),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
