<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « redaction » : ecrit l'article anglais a partir du seul dossier de recherche. Aucun outil : un
 * contenu piege lu sur le web ne peut rien declencher ici (cahier des charges, § 11).
 */
final class WriterAgent extends BlogAgent implements HasStructuredOutput
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
        $image = static fn (JsonSchema $schema) => $schema->object([
            'description' => $schema->string()->description('What the image shows, used as its alt text.')->required(),
            'search_query' => $schema->string()->description('Two or three English words naming the main subject, to search a stock photo.')->required(),
            'generation_prompt' => $schema->string()->description('A short prompt to generate the image.')->required(),
        ]);

        return [
            ...self::articleSchema($schema),
            'claims' => $schema->array()->items($schema->object([
                'statement' => $schema->string()->description('A legal or factual statement of the article, as written.')->required(),
                'basis' => $schema->array()->items($schema->string())->description('The ids of the facts that support it (F1...), or RULES for the reference of EU product rules.')->required(),
            ]))->description('Every legal or factual statement of the article, each with its basis.')->required(),
            'source_ids' => $schema->array()->items($schema->string())->description('The sources shown under the article, three most credible, official first.')->required(),
            'extra_sources_reason' => $schema->string()->description('Why a fourth or fifth source is needed. Empty otherwise.')->nullable(),
            'related_post_ids' => $schema->array()->items($schema->integer()),
            'featured_image' => $image($schema)->required(),
            'inline_images' => $schema->array()->items($schema->object([
                'number' => $schema->integer()->required(),
                'description' => $schema->string()->required(),
                'search_query' => $schema->string()->required(),
                'generation_prompt' => $schema->string()->required(),
            ])),
            'report_fr' => $schema->string()->required(),
        ];
    }

    /**
     * Les champs d'un article, communs a la redaction, a la verification, au style et aux traductions.
     *
     * @return array<string, mixed>
     */
    public static function articleSchema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'excerpt' => $schema->string()->description('Two or three sentences shown on the blog cards.')->required(),
            'meta_title' => $schema->string()->required(),
            'meta_description' => $schema->string()->description('140 to 160 characters.')->required(),
            'body_markdown' => $schema->string()->required(),
        ];
    }
}
