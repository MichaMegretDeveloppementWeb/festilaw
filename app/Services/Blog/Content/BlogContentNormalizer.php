<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

use App\Data\Blog\NormalizedBlogContent;
use App\Exceptions\Blog\BlogContentException;
use App\Services\Blog\Content\BlogContentSchema as Schema;

/**
 * Valide un document de contenu et le ramene a sa forme canonique. Calcul pur.
 *
 * - Tout element, marque ou attribut absent du modele ferme (BlogContentSchema) est retire.
 * - Les elements vides disparaissent, les textes voisins de memes marques sont fusionnes : deux
 *   documents equivalents ont la meme forme, ce qui permet de les comparer.
 * - Ce qui n'est pas un document, ou depasse les plafonds, est refuse (exception typee).
 *
 * Les compteurs internes ne vivent que le temps d'un appel a normalize() : ils sont remis a zero a
 * chaque appel, le resultat ne depend que du document recu.
 */
final class BlogContentNormalizer
{
    /** Une image n'est admise qu'au premier niveau du document, seule ou avec son texte. */
    private const TOP_LEVEL_BLOCKS = [
        Schema::PARAGRAPH, Schema::HEADING, Schema::BULLET_LIST, Schema::ORDERED_LIST, Schema::BLOCKQUOTE, Schema::IMAGE, Schema::IMAGE_TEXT,
    ];

    private const LIST_ITEM_BLOCKS = [Schema::PARAGRAPH, Schema::BULLET_LIST, Schema::ORDERED_LIST];

    /** Ce que le texte a cote d'une image peut contenir (choix de Micha du 19/11) : ni image, ni citation. */
    private const SIDE_TEXT_BLOCKS = [Schema::PARAGRAPH, Schema::HEADING, Schema::BULLET_LIST, Schema::ORDERED_LIST];

    /** Bloc « image et texte » sans largeur ou sans cote connus : une petite image, a gauche. */
    private const SIDE_DEFAULT_WIDTH = 'small';

    private const SIDE_DEFAULT_SIDE = 'left';

    private int $nodeCount = 0;

    /** @var array<int, true> */
    private array $imageIds = [];

    /** @var list<string> */
    private array $texts = [];

    public function __construct(
        private readonly BlogLinkPolicy $links,
        private readonly int $maxBytes = 200_000,
        private readonly int $maxNodes = 3_000,
        private readonly int $maxDepth = 12,
    ) {}

    /**
     * @param  mixed  $document  document de l'editeur, deja decode (tableau), ou null
     *
     * @throws BlogContentException
     */
    public function normalize(mixed $document): NormalizedBlogContent
    {
        if ($document === null || $document === []) {
            return NormalizedBlogContent::empty();
        }

        if (! is_array($document) || ($document['type'] ?? null) !== Schema::DOCUMENT) {
            throw BlogContentException::notADocument();
        }

        $bytes = strlen((string) json_encode($document));

        if ($bytes > $this->maxBytes) {
            throw BlogContentException::tooLarge($bytes, $this->maxBytes);
        }

        $this->nodeCount = 0;
        $this->imageIds = [];
        $this->texts = [];

        $blocks = $this->blocks($document['content'] ?? [], 1, self::TOP_LEVEL_BLOCKS);

        if ($blocks === []) {
            return NormalizedBlogContent::empty();
        }

        return new NormalizedBlogContent(
            document: ['type' => Schema::DOCUMENT, 'content' => $blocks],
            imageIds: array_keys($this->imageIds),
            wordCount: $this->countWords(),
        );
    }

    /**
     * Blocs d'un conteneur. Seuls les types admis a cet endroit sont traites : un intertitre dans une
     * liste ou une image dans une citation sont ignores, sans laisser de trace (ni mots comptes, ni
     * image citee).
     *
     * @param  list<string>  $allowed
     * @return list<array<string, mixed>>
     */
    private function blocks(mixed $nodes, int $depth, array $allowed): array
    {
        $this->guardDepth($depth);

        $blocks = [];

        foreach ($this->children($nodes) as $node) {
            $type = $node['type'] ?? null;

            if (! in_array($type, $allowed, true)) {
                continue;
            }

            $block = match ($type) {
                Schema::PARAGRAPH => $this->paragraph($node, $depth),
                Schema::HEADING => $this->heading($node, $depth),
                Schema::BULLET_LIST, Schema::ORDERED_LIST => $this->list($node, $depth),
                Schema::BLOCKQUOTE => $this->blockquote($node, $depth),
                Schema::IMAGE => $this->image($node),
                Schema::IMAGE_TEXT => $this->imageText($node, $depth),
                default => null,
            };

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function paragraph(array $node, int $depth): ?array
    {
        $inline = $this->inline($node['content'] ?? [], $depth + 1);

        if (! $this->hasVisibleText($inline)) {
            return null;
        }

        $this->texts[] = $this->plainText($inline);

        return ['type' => Schema::PARAGRAPH, 'content' => $inline];
    }

    /**
     * Un intertitre ne garde que son texte (ni gras, ni lien, ni saut de ligne) ; son niveau est
     * ramene a 2 ou 3.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function heading(array $node, int $depth): ?array
    {
        $text = trim($this->plainText($this->inline($node['content'] ?? [], $depth + 1)));

        if ($text === '') {
            return null;
        }

        $level = (int) (is_array($node['attrs'] ?? null) ? ($node['attrs']['level'] ?? 2) : 2);
        $this->texts[] = $text;

        return [
            'type' => Schema::HEADING,
            'attrs' => ['level' => $level <= 2 ? 2 : 3],
            'content' => [['type' => Schema::TEXT, 'text' => $text]],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function list(array $node, int $depth): ?array
    {
        $this->guardDepth($depth + 1);

        $items = [];

        foreach ($this->children($node['content'] ?? []) as $item) {
            if (($item['type'] ?? null) !== Schema::LIST_ITEM) {
                continue;
            }

            $content = $this->blocks($item['content'] ?? [], $depth + 2, self::LIST_ITEM_BLOCKS);

            if ($content !== []) {
                $items[] = ['type' => Schema::LIST_ITEM, 'content' => $content];
            }
        }

        return $items === [] ? null : ['type' => $node['type'], 'content' => $items];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function blockquote(array $node, int $depth): ?array
    {
        $paragraphs = $this->blocks($node['content'] ?? [], $depth + 1, [Schema::PARAGRAPH]);

        return $paragraphs === [] ? null : ['type' => Schema::BLOCKQUOTE, 'content' => $paragraphs];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function image(array $node): ?array
    {
        $imageId = is_array($node['attrs'] ?? null) ? ($node['attrs']['imageId'] ?? null) : null;

        if (! is_int($imageId) && ! (is_string($imageId) && ctype_digit($imageId))) {
            return null;
        }

        $imageId = (int) $imageId;

        if ($imageId <= 0) {
            return null;
        }

        $this->imageIds[$imageId] = true;

        $attrs = ['imageId' => $imageId];
        $width = $node['attrs']['width'] ?? null;

        // Pleine largeur : aucun attribut. Une largeur inconnue est retiree, l'image reprend toute la colonne. Une
        // image seule n'a pas de position : placee a gauche ou a droite, elle devient un bloc « image et texte ».
        if (is_string($width) && in_array($width, Schema::IMAGE_WIDTHS, true)) {
            $attrs['width'] = $width;
        }

        return ['type' => Schema::IMAGE, 'attrs' => $attrs];
    }

    /**
     * Bloc « image et texte » : l'image, toujours reduite, et son texte de l'autre cote. Le texte garde des
     * paragraphes, des listes et des intertitres, ramenes au niveau 3 pour rester sous ceux de l'article. Un
     * texte vide garde un paragraphe vide, ou l'editeur place le curseur. Sans image valable, le bloc disparait.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function imageText(array $node, int $depth): ?array
    {
        $image = $this->image($node);

        if ($image === null) {
            return null;
        }

        $side = $node['attrs']['side'] ?? null;

        $content = array_map(
            static fn (array $block): array => $block['type'] === Schema::HEADING ? [...$block, 'attrs' => ['level' => 3]] : $block,
            $this->blocks($node['content'] ?? [], $depth + 1, self::SIDE_TEXT_BLOCKS),
        );

        return [
            'type' => Schema::IMAGE_TEXT,
            'attrs' => [
                'imageId' => $image['attrs']['imageId'],
                'width' => $image['attrs']['width'] ?? self::SIDE_DEFAULT_WIDTH,
                'side' => is_string($side) && in_array($side, Schema::IMAGE_SIDES, true) ? $side : self::SIDE_DEFAULT_SIDE,
            ],
            'content' => $content === [] ? [['type' => Schema::PARAGRAPH]] : $content,
        ];
    }

    /**
     * Texte et sauts de ligne d'un bloc : marques filtrees et mises dans l'ordre canonique, textes
     * voisins de memes marques fusionnes.
     *
     * @return list<array<string, mixed>>
     */
    private function inline(mixed $nodes, int $depth): array
    {
        $this->guardDepth($depth);

        $inline = [];

        foreach ($this->children($nodes) as $node) {
            $type = $node['type'] ?? null;

            if ($type === Schema::HARD_BREAK) {
                $inline[] = ['type' => Schema::HARD_BREAK];

                continue;
            }

            if ($type !== Schema::TEXT || ! is_string($node['text'] ?? null) || $node['text'] === '') {
                continue;
            }

            $marks = $this->marks($node['marks'] ?? []);
            $last = array_key_last($inline);

            if ($last !== null && $inline[$last]['type'] === Schema::TEXT && ($inline[$last]['marks'] ?? []) === $marks) {
                $inline[$last]['text'] .= $node['text'];

                continue;
            }

            $inline[] = $marks === []
                ? ['type' => Schema::TEXT, 'text' => $node['text']]
                : ['type' => Schema::TEXT, 'text' => $node['text'], 'marks' => $marks];
        }

        return $inline;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marks(mixed $marks): array
    {
        $kept = [];

        foreach (is_array($marks) ? $marks : [] as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;

            if ($type === Schema::MARK_BOLD || $type === Schema::MARK_ITALIC) {
                $kept[$type] = ['type' => $type];
            }

            if ($type === Schema::MARK_LINK) {
                $href = is_array($mark['attrs'] ?? null) ? ($mark['attrs']['href'] ?? null) : null;

                if (is_string($href) && $this->links->isAllowed($href)) {
                    $kept[$type] = ['type' => $type, 'attrs' => ['href' => $href]];
                }
            }
        }

        $ordered = [];

        foreach (Schema::MARK_ORDER as $type) {
            if (isset($kept[$type])) {
                $ordered[] = $kept[$type];
            }
        }

        return $ordered;
    }

    /**
     * Enfants exploitables d'un noeud, en comptant chaque noeud rencontre.
     *
     * @return list<array<string, mixed>>
     */
    private function children(mixed $nodes): array
    {
        if (! is_array($nodes)) {
            return [];
        }

        $children = [];

        foreach ($nodes as $node) {
            if (++$this->nodeCount > $this->maxNodes) {
                throw BlogContentException::tooManyNodes($this->maxNodes);
            }

            if (is_array($node)) {
                $children[] = $node;
            }
        }

        return $children;
    }

    private function guardDepth(int $depth): void
    {
        if ($depth > $this->maxDepth) {
            throw BlogContentException::tooDeep($this->maxDepth);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $inline
     */
    private function hasVisibleText(array $inline): bool
    {
        return trim($this->plainText($inline)) !== '';
    }

    /**
     * @param  list<array<string, mixed>>  $inline
     */
    private function plainText(array $inline): string
    {
        $text = '';

        foreach ($inline as $node) {
            $text .= $node['type'] === Schema::TEXT ? $node['text'] : ' ';
        }

        return $text;
    }

    /** Un mot = une suite de caracteres contenant au moins une lettre ou un chiffre. */
    private function countWords(): int
    {
        $count = 0;

        foreach ($this->texts as $text) {
            foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (preg_match('/[\p{L}\p{N}]/u', $token) === 1) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
