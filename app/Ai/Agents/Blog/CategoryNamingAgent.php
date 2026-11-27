<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Premier temps du choix de la categorie (proposition de Micha du 27/11) : nomme le sujet principal d'un article
 * deja ecrit, comme le nom d'une categorie, sans connaitre les categories du blog. Il ne peut donc etre tire vers
 * aucune d'elles. La comparaison avec les categories existantes vient ensuite (CategoryMatchingAgent).
 *
 * Limite de longueur : une reponse de quelques dizaines de jetons, plus une reflexion courte.
 */
#[MaxTokens(2000)]
final class CategoryNamingAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The main subject of the article, named as a blog category: 1 to 4 English words, in sentence case.')->required(),
            'reason' => $schema->string()->description('One English sentence saying why this is the main subject of the article.')->required(),
        ];
    }
}
