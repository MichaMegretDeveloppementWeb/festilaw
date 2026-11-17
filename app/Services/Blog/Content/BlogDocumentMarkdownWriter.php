<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

use App\Services\Blog\Content\BlogContentSchema as Schema;

/**
 * Ecrit un document de l'editeur en Markdown restreint, l'inverse de BlogMarkdownConverter, pour le donner a
 * traduire a un modele (traduction assistee, SC6). Les images deviennent des emplacements `[[image:N]]`
 * numerotes dans l'ordre, et leurs attributs (image, largeur, position) sont rendus a part : la traduction
 * les retrouve tels quels. Le texte est echappe, pour qu'un asterisque ou un crochet ecrit par la personne
 * ne devienne pas une mise en forme.
 */
final class BlogDocumentMarkdownWriter
{
    /**
     * @param  array<string, mixed>|null  $document
     * @return array{markdown: string, images: array<int, array<string, mixed>>}
     */
    public function write(?array $document): array
    {
        $images = [];
        $blocks = [];

        foreach ($document['content'] ?? [] as $node) {
            $block = $this->block($node, $images);

            if ($block !== '') {
                $blocks[] = $block;
            }
        }

        return ['markdown' => implode("\n\n", $blocks), 'images' => $images];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $images
     */
    private function block(array $node, array &$images): string
    {
        return match ($node['type'] ?? null) {
            Schema::PARAGRAPH => $this->inline($node['content'] ?? []),
            Schema::HEADING => str_repeat('#', ((int) ($node['attrs']['level'] ?? 2)) === 3 ? 3 : 2).' '.$this->inline($node['content'] ?? []),
            Schema::BULLET_LIST => $this->listItems($node['content'] ?? [], false, $images),
            Schema::ORDERED_LIST => $this->listItems($node['content'] ?? [], true, $images),
            Schema::BLOCKQUOTE => $this->quote($node['content'] ?? [], $images),
            Schema::IMAGE => $this->image($node, $images),
            default => '',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $images
     */
    private function listItems(array $items, bool $ordered, array &$images): string
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $marker = $ordered ? ($index + 1).'. ' : '- ';
            $parts = [];

            foreach ($item['content'] ?? [] as $child) {
                $parts[] = $this->block($child, $images);
            }

            // Les lignes suivantes d'un element (liste imbriquee) sont decalees sous le texte de l'element.
            $text = implode("\n", array_filter($parts, static fn (string $part): bool => $part !== ''));
            $lines[] = $marker.str_replace("\n", "\n".str_repeat(' ', strlen($marker)), $text);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $images
     */
    private function quote(array $children, array &$images): string
    {
        $text = implode("\n\n", array_map(fn (array $child): string => $this->block($child, $images), $children));

        return '> '.str_replace("\n", "\n> ", $text);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $images
     */
    private function image(array $node, array &$images): string
    {
        $number = count($images) + 1;
        $images[$number] = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        return "[[image:{$number}]]";
    }

    /** @param  list<array<string, mixed>>  $nodes */
    private function inline(array $nodes): string
    {
        $text = '';

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === Schema::HARD_BREAK) {
                $text .= "\\\n";

                continue;
            }

            $value = $this->escape((string) ($node['text'] ?? ''));
            $marks = collect($node['marks'] ?? [])->keyBy('type');

            if ($marks->has(Schema::MARK_ITALIC)) {
                $value = '*'.$value.'*';
            }

            if ($marks->has(Schema::MARK_BOLD)) {
                $value = '**'.$value.'**';
            }

            if ($marks->has(Schema::MARK_LINK)) {
                $value = '['.$value.']('.($marks->get(Schema::MARK_LINK)['attrs']['href'] ?? '').')';
            }

            $text .= $value;
        }

        return $text;
    }

    private function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_\[\]])/', '\\\\$1', $text);
    }
}
