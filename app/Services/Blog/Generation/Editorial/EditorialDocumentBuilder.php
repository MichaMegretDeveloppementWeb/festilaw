<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Services\Blog\Content\BlogContentSchema;
use App\Services\Blog\Content\BlogMarkdownConverter;

/**
 * Le document de l'editeur a partir du Markdown d'un agent : converti par le meme lecteur qu'un texte saisi,
 * puis chaque emplacement `[[image:N]]` seul dans son paragraphe remplace par l'image, ou retire quand
 * l'image manque. Sert a l'assemblage d'un brouillon genere et a la traduction assistee.
 */
final readonly class EditorialDocumentBuilder
{
    public function __construct(private BlogMarkdownConverter $markdown) {}

    /**
     * @param  array<int, array<string, mixed>>  $images  numero de l'emplacement => attributs du noeud image (imageId, width, align)
     * @return array<string, mixed>
     */
    public function build(string $markdown, array $images): array
    {
        $document = $this->markdown->toDocument($markdown)->document ?? ['type' => BlogContentSchema::DOCUMENT, 'content' => []];
        $content = [];
        $placeholder = '/^'.trim(EditorialMarkdown::IMAGE_PLACEHOLDER, '~').'$/';

        foreach ($document['content'] ?? [] as $node) {
            $text = ($node['type'] ?? null) === BlogContentSchema::PARAGRAPH && count($node['content'] ?? []) === 1
                ? trim((string) ($node['content'][0]['text'] ?? ''))
                : null;

            if ($text !== null && preg_match($placeholder, $text, $match) === 1) {
                if (isset($images[(int) $match[1]])) {
                    $content[] = ['type' => BlogContentSchema::IMAGE, 'attrs' => $images[(int) $match[1]]];
                }

                continue;
            }

            $content[] = $node;
        }

        return [...$document, 'content' => $content];
    }
}
