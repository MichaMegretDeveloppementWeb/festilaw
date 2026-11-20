<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Regarde une photo de la banque d'images, jointe a la demande, et dit si elle convient a l'article ; puis la
 * decrit telle qu'elle est, en trois langues, pour son texte alternatif. Remarque de Micha du 20/11 : le code
 * prenait la photo la plus populaire de la recherche et la decrivait par l'image voulue, d'ou des outils sur
 * un etabli decrits comme un jouet en bois.
 *
 * Limite de longueur : une reponse d'environ 200 jetons, plus la reflexion du niveau d'effort eleve
 * (`medium` pour cette etape).
 */
#[MaxTokens(2000)]
final class PhotoCheckAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'fits' => $schema->boolean()->description('True only if the photo clearly shows a subject that suits the article.')->required(),
            'reason' => $schema->string()->description('Why it fits or not, in one short English sentence.')->required(),
            'alt_en' => $schema->string()->description('What the photo actually shows, one plain English sentence.')->required(),
            'alt_fr' => $schema->string()->description('The same description, in French.')->required(),
            'alt_es' => $schema->string()->description('The same description, in Spanish.')->required(),
        ];
    }
}
