<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Decrit l'image principale a trouver pour un article deja ecrit (demande de Micha du 24/11/2026) : ce qu'elle
 * montre, la recherche pour la banque d'images, la consigne pour un generateur, et ses descriptions dans les
 * trois langues. Memes regles d'image que la redaction (WriterAgent). Quand l'article a deja une image, il en
 * propose une autre.
 *
 * Limite de longueur : une reponse d'environ 300 jetons, plus une reflexion courte.
 */
#[MaxTokens(2000)]
final class ImageBriefAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->description('What the image shows, one plain English sentence, used as its alt text.')->required(),
            'search_query' => $schema->string()->description('Two or three English words naming the main subject, to search a stock photo.')->required(),
            'generation_prompt' => $schema->string()->description('The image described for an image generator, in one or two English sentences.')->required(),
            'alt_fr' => $schema->string()->description('The description, in French.')->required(),
            'alt_es' => $schema->string()->description('The description, in Spanish.')->required(),
            'report_fr' => $schema->string()->description('One sentence in French for the editor about the image chosen.')->required(),
        ];
    }
}
