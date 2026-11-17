<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « relecture du style » : recrit les tournures d'un texte genere (annexe A.12) sans toucher a un fait.
 * Le code compare ensuite les deux versions et ecarte la sienne si un chiffre ou un lien a change.
 */
final class StyleAgent extends BlogAgent implements HasStructuredOutput
{
    public function __construct(string $instructions = '', private readonly ?int $answerWords = null)
    {
        parent::__construct($instructions);
    }

    public function maxTokens(): int
    {
        return self::tokensForArticle($this->answerWords);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...WriterAgent::articleSchema($schema),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
