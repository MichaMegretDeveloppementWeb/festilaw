<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Met en forme le dossier de faits a partir des notes de recherche. Sans outil : une sortie structuree ne
 * tient pas avec la recherche web (essai reel du 17/11).
 *
 * Limite de longueur : le dossier tire de 900 mots de notes fait environ 2 500 jetons, et cette etape ne
 * reflechit pas, ou peu au niveau d'effort eleve (`blog.ai.effort`). Au 17/11, 6 000 jetons avec la reflexion
 * par defaut de Haiku 5.5 coupaient la reponse.
 */
#[MaxTokens(8000)]
final class ResearchAgent extends BlogAgent implements HasStructuredOutput
{
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
