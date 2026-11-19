<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

use App\Models\BlogImage;
use App\Services\Blog\Content\BlogContentSchema as Schema;
use App\Services\Blog\Image\BlogImagePaths;
use App\Services\Blog\Slug\BlogSlugGenerator;

/**
 * Produit le HTML d'un document de contenu DEJA normalise (cf. BlogContentNormalizer). Calcul pur :
 * les images citees sont fournies par l'appelant, aucune lecture en base ni sur le disque.
 *
 * Tout texte est echappe ; le HTML ne contient que des balises ecrites ici. C'est ce HTML qui est
 * enregistre puis servi au public.
 */
final class BlogContentRenderer
{
    /** Largeur de la variante servie par defaut (attribut src) quand elle existe. */
    private const DEFAULT_WIDTH = 1200;

    /** @var array<string, int> */
    private array $anchors = [];

    public function __construct(
        private readonly BlogImagePaths $paths,
        private readonly BlogSlugGenerator $slugs,
        private readonly BlogLinkPolicy $links,
        private readonly string $imageSizes = '100vw',
        /** @var array<string, int> largeur maximale a l'ecran, en pixels, de chaque largeur reduite */
        private readonly array $reducedWidths = [],
    ) {}

    /**
     * @param  array{type: string, content: list<array<string, mixed>>}|null  $document
     * @param  iterable<BlogImage>  $images  images citees par le document (les autres sont ignorees)
     */
    public function render(?array $document, string $locale, iterable $images = []): string
    {
        if ($document === null) {
            return '';
        }

        $this->anchors = [];

        $imagesById = [];

        foreach ($images as $image) {
            $imagesById[$image->id] = $image;
        }

        return implode("\n", array_filter(
            array_map(fn (array $block): string => $this->block($block, $locale, $imagesById), $document['content']),
            static fn (string $html): bool => $html !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<int, BlogImage>  $images
     */
    private function block(array $block, string $locale, array $images): string
    {
        return match ($block['type']) {
            Schema::PARAGRAPH => '<p>'.$this->inline($block['content']).'</p>',
            Schema::HEADING => $this->heading($block),
            Schema::BULLET_LIST => '<ul>'.$this->items($block['content'], $locale, $images).'</ul>',
            Schema::ORDERED_LIST => '<ol>'.$this->items($block['content'], $locale, $images).'</ol>',
            Schema::BLOCKQUOTE => '<blockquote>'.implode('', array_map(fn (array $child): string => $this->block($child, $locale, $images), $block['content'])).'</blockquote>',
            Schema::IMAGE => $this->figure($images[$block['attrs']['imageId']] ?? null, $locale, $block['attrs']['width'] ?? null),
            Schema::IMAGE_TEXT => $this->imageText($block, $locale, $images),
            default => '',
        };
    }

    /**
     * Bloc « image et texte » : la figure puis son texte, dans un conteneur que la feuille de la page met en
     * deux colonnes quand la colonne de l'article est assez large (resources/css/web/blog/prose.css), et l'un
     * sous l'autre en dessous. Le cote est une classe : l'ordre du HTML reste le meme, image d'abord. Une image
     * introuvable laisse son texte, rendu comme le reste de l'article.
     *
     * @param  array<string, mixed>  $block
     * @param  array<int, BlogImage>  $images
     */
    private function imageText(array $block, string $locale, array $images): string
    {
        $text = implode('', array_map(
            fn (array $child): string => $child['type'] === Schema::PARAGRAPH && ($child['content'] ?? []) === [] ? '' : $this->block($child, $locale, $images),
            $block['content'],
        ));

        $figure = $this->figure($images[$block['attrs']['imageId']] ?? null, $locale, $block['attrs']['width']);

        if ($figure === '') {
            return $text;
        }

        $class = "blog-figure-text blog-figure-text--{$block['attrs']['width']} blog-figure-text--{$block['attrs']['side']}";
        $body = $text === '' ? '' : "<div class=\"blog-figure-text__body\">{$text}</div>";

        return "<div class=\"{$class}\">{$figure}{$body}</div>";
    }

    /**
     * Intertitre avec une ancre tiree de son texte, unique dans la page.
     *
     * @param  array<string, mixed>  $block
     */
    private function heading(array $block): string
    {
        $level = $block['attrs']['level'];
        $text = $block['content'][0]['text'];

        $anchor = $this->slugs->fromText($text);
        $anchor = $anchor === '' ? 'section' : $anchor;

        $this->anchors[$anchor] = ($this->anchors[$anchor] ?? 0) + 1;

        if ($this->anchors[$anchor] > 1) {
            $anchor .= '-'.$this->anchors[$anchor];
        }

        return "<h{$level} id=\"{$this->escape($anchor)}\">{$this->escape($text)}</h{$level}>";
    }

    /**
     * Elements d'une liste. Un element fait d'un seul paragraphe (suivi ou non de sous-listes) est
     * rendu sans balise <p>, comme une liste ordinaire.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, BlogImage>  $images
     */
    private function items(array $items, string $locale, array $images): string
    {
        $html = '';

        foreach ($items as $item) {
            $paragraphs = array_filter($item['content'], static fn (array $child): bool => $child['type'] === Schema::PARAGRAPH);
            $tight = count($paragraphs) === 1 && $item['content'][0]['type'] === Schema::PARAGRAPH;

            $html .= '<li>';

            foreach ($item['content'] as $child) {
                $html .= $tight && $child['type'] === Schema::PARAGRAPH
                    ? $this->inline($child['content'])
                    : $this->block($child, $locale, $images);
            }

            $html .= '</li>';
        }

        return $html;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function inline(array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            if ($node['type'] === Schema::HARD_BREAK) {
                $html .= '<br>';

                continue;
            }

            $text = $this->escape($node['text']);

            // Marques dans l'ordre canonique (lien, gras, italique) : le lien enveloppe le reste.
            foreach (array_reverse($node['marks'] ?? []) as $mark) {
                $text = match ($mark['type']) {
                    Schema::MARK_ITALIC => "<em>{$text}</em>",
                    Schema::MARK_BOLD => "<strong>{$text}</strong>",
                    Schema::MARK_LINK => $this->link($mark['attrs']['href'], $text),
                    default => $text,
                };
            }

            $html .= $text;
        }

        return $html;
    }

    private function link(string $href, string $html): string
    {
        $attributes = 'href="'.$this->escape($href).'"';

        if ($this->links->isExternal($href)) {
            $attributes .= ' target="_blank" rel="noopener"';
        }

        return "<a {$attributes}>{$html}</a>";
    }

    /**
     * Image adaptative. Une image citee mais introuvable n'est pas rendue. Une image reduite porte une
     * classe de plus, et annonce au navigateur sa largeur a l'ecran pour qu'il charge une variante plus
     * legere.
     */
    private function figure(?BlogImage $image, string $locale, ?string $width = null): string
    {
        if ($image === null) {
            return '';
        }

        $widths = $image->widths();

        if ($widths === []) {
            return '';
        }

        $default = in_array(self::DEFAULT_WIDTH, $widths, true) ? self::DEFAULT_WIDTH : max($widths);
        $reduced = $width === null ? null : ($this->reducedWidths[$width] ?? null);

        $srcset = implode(', ', array_map(
            fn (int $width): string => $this->paths->url($this->paths->variantOf($image, $width))." {$width}w",
            $widths,
        ));

        $attributes = [
            'src' => $this->paths->url($this->paths->variantOf($image, $default)),
            'srcset' => $srcset,
            'sizes' => $reduced === null ? $this->imageSizes : "(min-width: {$reduced}px) {$reduced}px, 100vw",
            'width' => (string) $image->width,
            'height' => (string) $image->height,
            'alt' => $image->altFor($locale),
            'loading' => 'lazy',
            'decoding' => 'async',
        ];

        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= " {$name}=\"{$this->escape($value)}\"";
        }

        $class = $reduced === null ? 'blog-figure' : "blog-figure blog-figure--{$width}";

        return "<figure class=\"{$class}\"><img{$html}></figure>";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
