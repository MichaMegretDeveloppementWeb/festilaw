<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « sujet », second temps : choisit le sujet d'un article, ou repond qu'aucun n'est assez pertinent, a
 * partir des notes de veille du premier temps (WebSearchAgent) et de la liste des titres connus. Sans outil :
 * une sortie structuree ne tient pas avec la recherche web (essai reel du 17/11).
 */
#[MaxTokens(2000)]
final class TopicAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'decision' => $schema->string()->enum(['topic', 'none'])->required(),
            'reason' => $schema->string()->description('Why this topic, or why no topic is relevant enough.')->required(),
            // Une liste de valeurs ne peut pas etre nulle dans un schema accepte par Anthropic (essai reel du 17/11).
            'kind' => $schema->string()->enum(['evergreen', 'news'])->required(),
            'title' => $schema->string()->description('Working title, about 60 characters.')->nullable(),
            'angle' => $schema->string()->nullable(),
            'reader' => $schema->string()->nullable(),
            'keywords' => $schema->array()->items($schema->string()),
            'report_fr' => $schema->string()->description('One or two sentences in French for the editor.')->required(),
        ];
    }
}
