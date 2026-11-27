<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Second temps du choix de la categorie : compare le sujet nomme sans voir les categories (CategoryNamingAgent)
 * aux categories du blog, chacune avec les titres de ses derniers articles. Il ne reprend une categorie que si
 * elle traite le meme sujet, pour qu'un meme sujet ne donne pas deux categories voisines (« Labelling » et
 * « Product labels »).
 *
 * Limite de longueur : une reponse de quelques dizaines de jetons, plus une reflexion courte.
 */
#[MaxTokens(2000)]
final class CategoryMatchingAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()->description('The id of the category of the list that has the same subject. Null when none has.')->nullable(),
            'reason' => $schema->string()->description('One English sentence saying why.')->required(),
        ];
    }
}
