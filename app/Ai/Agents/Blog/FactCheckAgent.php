<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « verification » : relit l'article contre le dossier et retire ou corrige toute affirmation que le
 * dossier n'etaye pas, sans rien ajouter. Ce qu'il retire va au journal de l'etape. Il signale a part les
 * rares affirmations gardees sur lesquelles un doute reste (points d'attention, en francais), que la page de
 * la generation montre (decision de Micha du 19/11).
 */
final class FactCheckAgent extends BlogAgent implements HasStructuredOutput
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
            'removed_claims' => $schema->array()->items($schema->object([
                'claim' => $schema->string()->required(),
                'reason' => $schema->string()->description('Why, in one plain French sentence for the editor.')->required(),
            ]))->required(),
            'attention_points' => $schema->array()->items($schema->object([
                'heading' => $schema->string()->description('The heading the statement stands under, copied exactly. Empty in the introduction.')->required(),
                'statement_fr' => $schema->string()->description('What the article says, in one plain French sentence.')->required(),
                'check_fr' => $schema->string()->description('What the editor should check, in one plain French sentence.')->required(),
            ]))->required(),
            'reference_conflicts' => $schema->array()->items($schema->object([
                'statement' => $schema->string()->description('The statement of the article that follows the reference, in English.')->required(),
                'source' => $schema->string()->description('The address of the official source of the web evidence that contradicts it.')->required(),
            ]))->required(),
            'report_fr' => $schema->string()->required(),
        ];
    }
}
