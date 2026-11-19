<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Services\Blog\Content\BlogContentSchema;
use App\Services\Blog\Content\BlogMarkdownConverter;

/**
 * Le document de l'editeur a partir du Markdown d'un agent : converti par le meme lecteur qu'un texte saisi,
 * puis chaque emplacement `[[image:N]]` seul dans son paragraphe remplace par l'image, ou retire quand
 * l'image manque. Sert a l'assemblage d'un brouillon genere et a la traduction assistee.
 *
 * Une image dont les attributs portent un cote (`side`) avait son texte a cote d'elle : les blocs qui suivent
 * son emplacement, jusqu'a sa fin `[[/image:N]]`, sont ranges dans un bloc « image et texte ». Sans fin, le
 * bloc garde un texte vide et le texte suivant reste a sa place : rien ne passe a cote de l'image par erreur.
 */
final readonly class EditorialDocumentBuilder
{
    /** Ce que le texte a cote d'une image admet (comme le serveur, BlogContentNormalizer). */
    private const SIDE_TEXT_BLOCKS = [BlogContentSchema::PARAGRAPH, BlogContentSchema::HEADING, BlogContentSchema::BULLET_LIST, BlogContentSchema::ORDERED_LIST];

    public function __construct(private BlogMarkdownConverter $markdown) {}

    /**
     * @param  array<int, array<string, mixed>>  $images  numero de l'emplacement => attributs du noeud image (imageId, width, side)
     * @return array<string, mixed>
     */
    public function build(string $markdown, array $images): array
    {
        $document = $this->markdown->toDocument($markdown)->document ?? ['type' => BlogContentSchema::DOCUMENT, 'content' => []];
        $nodes = array_values($document['content'] ?? []);
        $content = [];

        for ($index = 0; $index < count($nodes); $index++) {
            $node = $nodes[$index];
            $opening = $this->marker($node, EditorialMarkdown::IMAGE_PLACEHOLDER);

            // Une fin sans emplacement devant elle n'est qu'un repere egare.
            if ($this->marker($node, EditorialMarkdown::IMAGE_END) !== null) {
                continue;
            }

            if ($opening === null) {
                $content[] = $node;

                continue;
            }

            $attrs = $images[$opening] ?? null;

            if ($attrs === null) {
                continue;
            }

            if (! isset($attrs['side'])) {
                $content[] = ['type' => BlogContentSchema::IMAGE, 'attrs' => $attrs];

                continue;
            }

            $end = $this->endOf($nodes, $index + 1, $opening);
            $text = $end === null ? [] : $this->sideText(array_slice($nodes, $index + 1, $end - $index - 1));

            $content[] = ['type' => BlogContentSchema::IMAGE_TEXT, 'attrs' => $attrs, 'content' => $text === [] ? [['type' => BlogContentSchema::PARAGRAPH]] : $text];
            $index = $end ?? $index;
        }

        return [...$document, 'content' => $content];
    }

    /**
     * Le numero du repere qu'un paragraphe porte, seul, ou null.
     *
     * @param  array<string, mixed>  $node
     */
    private function marker(array $node, string $pattern): ?int
    {
        if (($node['type'] ?? null) !== BlogContentSchema::PARAGRAPH || count($node['content'] ?? []) !== 1) {
            return null;
        }

        $text = trim((string) ($node['content'][0]['text'] ?? ''));

        return preg_match('~^'.trim($pattern, '~').'$~', $text, $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * La position de la fin du texte de l'image numero $number, a partir de $from, ou null.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function endOf(array $nodes, int $from, int $number): ?int
    {
        for ($index = $from; $index < count($nodes); $index++) {
            if ($this->marker($nodes[$index], EditorialMarkdown::IMAGE_END) === $number) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Les blocs admis a cote d'une image, les intertitres ramenes au niveau 3.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function sideText(array $nodes): array
    {
        $text = [];

        foreach ($nodes as $node) {
            if (! in_array($node['type'] ?? null, self::SIDE_TEXT_BLOCKS, true)) {
                continue;
            }

            $text[] = ($node['type'] ?? null) === BlogContentSchema::HEADING ? [...$node, 'attrs' => ['level' => 3]] : $node;
        }

        return $text;
    }
}
