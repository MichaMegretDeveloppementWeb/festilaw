<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etapes « controle des versions » : compare une version francaise ou espagnole au texte anglais verifie et
 * rend la liste des passages qui disent autre chose sur un fait, avec leur correction. Il ne rend pas l'article :
 * le code applique les corrections, le style de la version ne peut donc pas bouger.
 */
final class VersionCheckAgent extends BlogAgent implements HasStructuredOutput
{
    /** Une liste de corrections, pas un article : la reponse reste courte, la reflexion comprise. */
    public function maxTokens(): int
    {
        return 8_000;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'corrections' => $schema->array()->items($schema->object([
                'original' => $schema->string()->description('The exact passage of the version, copied character for character.')->required(),
                'corrected' => $schema->string()->description('The same passage, rewritten so that it says what the English article says.')->required(),
                'reason' => $schema->string()->description('Why, in one plain French sentence for the editor.')->required(),
            ]))->required(),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
