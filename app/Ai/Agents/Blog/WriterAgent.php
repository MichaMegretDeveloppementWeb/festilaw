<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Etape « redaction » : ecrit l'article anglais a partir du seul dossier de recherche. Aucun outil : un
 * contenu piege lu sur le web ne peut rien declencher ici (cahier des charges, § 11).
 *
 * Limite de 16 000 jetons, reflexion comprise : DeepSeek V4.1 Flash ignore l'effort « low » et a reflechi
 * jusqu'a 9 000 jetons le 17/11 (generation 77), ce qui coupait la reponse. Decision de Micha du 17/11 :
 * garder la reflexion et relever la limite.
 */
#[MaxTokens(16000)]
final class WriterAgent extends BlogAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        $image = static fn (JsonSchema $schema) => $schema->object([
            'description' => $schema->string()->description('What the image shows, used as its alt text.')->required(),
            'search_query' => $schema->string()->description('A few English words to search a stock photo.')->required(),
            'generation_prompt' => $schema->string()->description('A short prompt to generate the image.')->required(),
        ]);

        return [
            ...self::articleSchema($schema),
            'source_ids' => $schema->array()->items($schema->string())->required(),
            'category_id' => $schema->integer()->nullable(),
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
