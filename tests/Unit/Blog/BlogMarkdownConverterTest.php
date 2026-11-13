<?php

use App\Exceptions\Blog\BlogContentException;
use App\Services\Blog\Content\BlogContentNormalizer;
use App\Services\Blog\Content\BlogLinkPolicy;
use App\Services\Blog\Content\BlogMarkdownConverter;

function blogMarkdown(string $markdown, int $maxBytes = 200_000): array
{
    $converter = new BlogMarkdownConverter(new BlogContentNormalizer(new BlogLinkPolicy), $maxBytes);

    return $converter->toDocument($markdown)->document['content'] ?? [];
}

it('converts headings, paragraphs and inline marks', function () {
    $blocks = blogMarkdown(<<<'MD'
    # Main title

    ### Details

    A **bold** and *italic* word with a [link](https://eur-lex.europa.eu/eli) and `code`.
    MD);

    expect($blocks)->toBe([
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Main title']]],
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Details']]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'A '],
            ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'text', 'text' => 'italic', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => ' word with a '],
            ['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://eur-lex.europa.eu/eli']]]],
            ['type' => 'text', 'text' => ' and code.'],
        ]],
    ]);
});

it('converts bullet, numbered and nested lists, and quotations', function () {
    $blocks = blogMarkdown(<<<'MD'
    - One
    - Two
        1. Two A

    > Quoted line
    MD);

    $paragraph = fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];

    expect($blocks)->toBe([
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [$paragraph('One')]],
            ['type' => 'listItem', 'content' => [
                $paragraph('Two'),
                ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [$paragraph('Two A')]]]],
            ]],
        ]],
        ['type' => 'blockquote', 'content' => [$paragraph('Quoted line')]],
    ]);
});

it('turns a soft line break into a space and a hard one into a break', function () {
    $blocks = blogMarkdown("first\nsecond  \nthird");

    expect($blocks)->toBe([
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'first second'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'third'],
        ]],
    ]);
});

it('ignores raw html, images, rules and forbidden links', function () {
    $blocks = blogMarkdown(<<<'MD'
    <script>alert(1)</script>

    Before <b onclick="x()">kept text</b> ![alt](https://evil.example/pixel.png) after.

    ---

    A [bad link](javascript:alert(1)) stays as text.
    MD);

    expect($blocks)->toBe([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Before kept text  after.']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A bad link stays as text.']]],
    ]);
});

it('keeps the text of a code block as a plain paragraph', function () {
    $blocks = blogMarkdown("```\nGPSR Article 16\n```");

    expect($blocks)->toBe([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'GPSR Article 16']]],
    ]);
});

it('reads an underlined line as a heading, as markdown defines it', function () {
    $blocks = blogMarkdown("Title on a line\n---\n\nBody.");

    expect($blocks[0])->toBe(['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Title on a line']]]);
});

it('returns empty content for an empty text', function () {
    expect(blogMarkdown("   \n\n"))->toBe([]);
});

it('refuses a text above the size limit before parsing it', function () {
    expect(fn () => blogMarkdown(str_repeat('word ', 300), maxBytes: 1_000))
        ->toThrow(BlogContentException::class, 'too large');
});
