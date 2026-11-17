<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « relecture du style » : recrit les tournures d'un texte genere (annexe A.12) sans toucher a un fait.
 * Le code compare ensuite les deux versions et ecarte la sienne si un chiffre ou un lien a change.
 *
 * Limite de 16 000 jetons, reflexion comprise : DeepSeek V4.1 Flash ignore l'effort « low » et a reflechi
 * jusqu'a 9 000 jetons le 17/11 (generation 77), ce qui coupait la reponse. Decision de Micha du 17/11 :
 * garder la reflexion et relever la limite.
 */
#[MaxTokens(16000)]
final class StyleAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...WriterAgent::articleSchema($schema),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
