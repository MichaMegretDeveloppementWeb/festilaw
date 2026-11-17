<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Etape « sujet » : choisit le sujet d'un article, ou repond qu'aucun n'est assez pertinent. Il peut chercher
 * sur le web pour reperer l'actualite reglementaire et les questions des vendeurs.
 */
#[MaxSteps(6)]
#[MaxTokens(2000)]
final class TopicAgent extends BlogAgent implements HasStructuredOutput, HasTools
{
    public function tools(): iterable
    {
        return [(new WebSearch)->max(5)];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'decision' => $schema->string()->enum(['topic', 'none'])->required(),
            'reason' => $schema->string()->description('Why this topic, or why no topic is relevant enough.')->required(),
            'kind' => $schema->string()->enum(['evergreen', 'news'])->nullable(),
            'title' => $schema->string()->description('Working title, about 60 characters.')->nullable(),
            'angle' => $schema->string()->nullable(),
            'reader' => $schema->string()->nullable(),
            'keywords' => $schema->array()->items($schema->string()),
            'report_fr' => $schema->string()->description('One or two sentences in French for the editor.')->required(),
        ];
    }
}
