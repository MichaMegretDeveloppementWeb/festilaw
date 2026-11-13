<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

use App\Data\Blog\NormalizedBlogContent;
use App\Exceptions\Blog\BlogContentException;
use App\Services\Blog\Content\BlogContentSchema as Schema;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Transforme un texte en Markdown restreint (celui que redige l'IA, ou un article de demonstration)
 * en document de contenu. Le Markdown n'est jamais rendu en HTML : on lit son arbre et on n'en garde
 * que les elements du modele ferme. HTML brut, images (elles porteraient une adresse externe),
 * tableaux et filets sont ignores. La sortie passe par BlogContentNormalizer, comme un document saisi.
 */
final readonly class BlogMarkdownConverter
{
    public function __construct(
        private BlogContentNormalizer $normalizer,
        private int $maxBytes = 200_000,
    ) {}

    /**
     * @throws BlogContentException
     */
    public function toDocument(string $markdown): NormalizedBlogContent
    {
        if (strlen($markdown) > $this->maxBytes) {
            throw BlogContentException::tooLarge(strlen($markdown), $this->maxBytes);
        }

        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);

        $tree = (new MarkdownParser($environment))->parse($markdown);

        return $this->normalizer->normalize([
            'type' => Schema::DOCUMENT,
            'content' => $this->blocks($tree),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(Node $parent): array
    {
        $blocks = [];

        foreach ($parent->children() as $node) {
            $block = match (true) {
                $node instanceof Paragraph => ['type' => Schema::PARAGRAPH, 'content' => $this->inline($node, [])],
                $node instanceof Heading => [
                    'type' => Schema::HEADING,
                    'attrs' => ['level' => $node->getLevel()],
                    'content' => $this->inline($node, []),
                ],
                $node instanceof BlockQuote => ['type' => Schema::BLOCKQUOTE, 'content' => $this->blocks($node)],
                $node instanceof ListBlock => [
                    'type' => $node->getListData()->type === ListBlock::TYPE_ORDERED ? Schema::ORDERED_LIST : Schema::BULLET_LIST,
                    'content' => $this->blocks($node),
                ],
                $node instanceof ListItem => ['type' => Schema::LIST_ITEM, 'content' => $this->blocks($node)],
                // Un bloc de code n'a pas sa place dans un article : son texte est garde comme paragraphe.
                $node instanceof FencedCode, $node instanceof IndentedCode => [
                    'type' => Schema::PARAGRAPH,
                    'content' => [['type' => Schema::TEXT, 'text' => trim($node->getLiteral())]],
                ],
                default => null,
            };

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @param  list<array<string, mixed>>  $marks  marques heritees des noeuds parents
     * @return list<array<string, mixed>>
     */
    private function inline(Node $parent, array $marks): array
    {
        $nodes = [];

        foreach ($parent->children() as $node) {
            if ($node instanceof Text || $node instanceof Code) {
                $nodes[] = $this->text($node->getLiteral(), $marks);
            } elseif ($node instanceof Newline) {
                $nodes[] = $node->getType() === Newline::HARDBREAK
                    ? ['type' => Schema::HARD_BREAK]
                    : $this->text(' ', $marks);
            } elseif ($node instanceof Strong) {
                array_push($nodes, ...$this->inline($node, [...$marks, ['type' => Schema::MARK_BOLD]]));
            } elseif ($node instanceof Emphasis) {
                array_push($nodes, ...$this->inline($node, [...$marks, ['type' => Schema::MARK_ITALIC]]));
            } elseif ($node instanceof Link) {
                array_push($nodes, ...$this->inline($node, [...$marks, ['type' => Schema::MARK_LINK, 'attrs' => ['href' => $node->getUrl()]]]));
            }
            // Image, HTML en ligne et tout autre noeud : ignores.
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return array<string, mixed>
     */
    private function text(string $text, array $marks): array
    {
        return $marks === []
            ? ['type' => Schema::TEXT, 'text' => $text]
            : ['type' => Schema::TEXT, 'text' => $text, 'marks' => $marks];
    }
}
