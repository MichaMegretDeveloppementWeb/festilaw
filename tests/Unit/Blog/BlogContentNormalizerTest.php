<?php

use App\Exceptions\Blog\BlogContentException;
use App\Services\Blog\Content\BlogContentNormalizer;
use App\Services\Blog\Content\BlogLinkPolicy;

function blogNormalizer(int $maxBytes = 200_000, int $maxNodes = 3_000, int $maxDepth = 12): BlogContentNormalizer
{
    return new BlogContentNormalizer(new BlogLinkPolicy, $maxBytes, $maxNodes, $maxDepth);
}

/** @param array<int, array<string, mixed>> $blocks */
function blogDoc(array ...$blocks): array
{
    return ['type' => 'doc', 'content' => $blocks];
}

/** @param array<int, array<string, mixed>> $marks */
function blogText(string $text, array $marks = []): array
{
    return $marks === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $marks];
}

function blogParagraph(array ...$inline): array
{
    return ['type' => 'paragraph', 'content' => $inline];
}

it('treats a missing or empty document as empty content', function (mixed $document) {
    $content = blogNormalizer()->normalize($document);

    expect($content->isEmpty())->toBeTrue()
        ->and($content->document)->toBeNull()
        ->and($content->wordCount)->toBe(0)
        ->and($content->imageIds)->toBe([]);
})->with([
    'null' => [null],
    'empty array' => [[]],
    'doc without content' => [['type' => 'doc']],
    'doc with only an empty paragraph' => [['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
    'doc with a blank paragraph' => [blogDoc(blogParagraph(blogText("  \n ")))],
]);

it('refuses what is not a document', function (mixed $document) {
    expect(fn () => blogNormalizer()->normalize($document))->toThrow(BlogContentException::class);
})->with([
    'a string' => ['<p>html</p>'],
    'a paragraph at the root' => [['type' => 'paragraph', 'content' => []]],
    'an array without type' => [['content' => []]],
]);

it('keeps the allowed elements untouched', function () {
    $document = blogDoc(
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [blogText('Labels')]],
        blogParagraph(blogText('Plain, '), blogText('bold', [['type' => 'bold']]), ['type' => 'hardBreak'], blogText('next line')),
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [blogParagraph(blogText('One'))]],
        ]],
        ['type' => 'orderedList', 'content' => [
            ['type' => 'listItem', 'content' => [blogParagraph(blogText('First'))]],
        ]],
        ['type' => 'blockquote', 'content' => [blogParagraph(blogText('Quoted'))]],
        ['type' => 'blogImage', 'attrs' => ['imageId' => 12]],
    );

    expect(blogNormalizer()->normalize($document)->document)->toBe($document);
});

it('keeps the width of an image of the text only when it is a known reduced width', function (mixed $width, array $expected) {
    $content = blogNormalizer()->normalize(blogDoc(['type' => 'blogImage', 'attrs' => ['imageId' => 12, 'width' => $width, 'onclick' => 'x']]));

    expect($content->document['content'][0]['attrs'])->toBe($expected);
})->with([
    'medium' => ['medium', ['imageId' => 12, 'width' => 'medium']],
    'small' => ['small', ['imageId' => 12, 'width' => 'small']],
    'full is the absence of width' => ['full', ['imageId' => 12]],
    'unknown' => ['huge', ['imageId' => 12]],
    'a number' => [360, ['imageId' => 12]],
    'nothing' => [null, ['imageId' => 12]],
]);

it('keeps the position of an image of the text only when the image is reduced and the position is known', function (array $attrs, array $expected) {
    $content = blogNormalizer()->normalize(blogDoc(['type' => 'blogImage', 'attrs' => ['imageId' => 12, ...$attrs]]));

    expect($content->document['content'][0]['attrs'])->toBe($expected);
})->with([
    'small on the left' => [['width' => 'small', 'align' => 'left'], ['imageId' => 12, 'width' => 'small', 'align' => 'left']],
    'medium on the right' => [['width' => 'medium', 'align' => 'right'], ['imageId' => 12, 'width' => 'medium', 'align' => 'right']],
    'centre is the absence of position' => [['width' => 'small', 'align' => 'center'], ['imageId' => 12, 'width' => 'small']],
    'unknown position' => [['width' => 'small', 'align' => 'top'], ['imageId' => 12, 'width' => 'small']],
    'no position at full width' => [['align' => 'left'], ['imageId' => 12]],
    'no position when the width is unknown' => [['width' => 'huge', 'align' => 'right'], ['imageId' => 12]],
]);

it('removes unknown elements, marks and attributes', function () {
    $content = blogNormalizer()->normalize(blogDoc(
        ['type' => 'codeBlock', 'content' => [blogText('rm -rf')]],
        ['type' => 'horizontalRule'],
        ['type' => 'table', 'content' => []],
        ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center', 'style' => 'color:red'], 'content' => [
            blogText('Underlined', [['type' => 'underline'], ['type' => 'code']]),
            ['type' => 'mention', 'attrs' => ['id' => 4]],
            blogText(' link', [['type' => 'link', 'attrs' => ['href' => 'https://example.com', 'target' => '_self', 'class' => 'x', 'onclick' => 'steal()']]]),
        ]],
    ));

    expect($content->document)->toBe(blogDoc(blogParagraph(
        blogText('Underlined'),
        blogText(' link', [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]),
    )));
});

it('drops a forbidden link but keeps its text', function () {
    $content = blogNormalizer()->normalize(blogDoc(blogParagraph(
        blogText('click', [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]),
    )));

    expect($content->document)->toBe(blogDoc(blogParagraph(blogText('click', [['type' => 'bold']]))));
});

it('brings headings back to plain text at level two or three', function (int $level, int $expected) {
    $content = blogNormalizer()->normalize(blogDoc([
        'type' => 'heading',
        'attrs' => ['level' => $level, 'id' => 'x'],
        'content' => [blogText('Bold ', [['type' => 'bold']]), ['type' => 'hardBreak'], blogText('title', [['type' => 'link', 'attrs' => ['href' => '/x']]])],
    ]));

    expect($content->document['content'][0])->toBe([
        'type' => 'heading',
        'attrs' => ['level' => $expected],
        'content' => [blogText('Bold  title')],
    ]);
})->with([[1, 2], [2, 2], [3, 3], [4, 3], [6, 3]]);

it('puts marks in a canonical order and merges neighbouring texts with the same marks', function () {
    $content = blogNormalizer()->normalize(blogDoc(blogParagraph(
        blogText('a', [['type' => 'italic'], ['type' => 'bold']]),
        blogText('b', [['type' => 'bold'], ['type' => 'italic'], ['type' => 'bold']]),
        blogText(''),
        blogText('c'),
        blogText('d'),
    )));

    expect($content->document)->toBe(blogDoc(blogParagraph(
        blogText('ab', [['type' => 'bold'], ['type' => 'italic']]),
        blogText('cd'),
    )));
});

it('gives the same result when applied twice', function () {
    $document = blogDoc(
        blogParagraph(blogText('x', [['type' => 'italic'], ['type' => 'bold']]), blogText('y', [['type' => 'bold'], ['type' => 'italic']])),
        ['type' => 'heading', 'attrs' => ['level' => 5], 'content' => [blogText('T')]],
    );

    $once = blogNormalizer()->normalize($document);
    $twice = blogNormalizer()->normalize($once->document);

    expect($twice->document)->toBe($once->document)->and($twice->wordCount)->toBe($once->wordCount);
});

it('accepts images only at the top level and lists each cited image once', function () {
    $content = blogNormalizer()->normalize(blogDoc(
        ['type' => 'blogImage', 'attrs' => ['imageId' => 7, 'src' => 'https://evil.example/x.png']],
        ['type' => 'blogImage', 'attrs' => ['imageId' => '9']],
        ['type' => 'blogImage', 'attrs' => ['imageId' => 7]],
        ['type' => 'blogImage', 'attrs' => ['imageId' => 0]],
        ['type' => 'blogImage', 'attrs' => ['imageId' => 'abc']],
        ['type' => 'blogImage'],
        ['type' => 'blockquote', 'content' => [['type' => 'blogImage', 'attrs' => ['imageId' => 99]], blogParagraph(blogText('Quote'))]],
    ));

    expect($content->imageIds)->toBe([7, 9])
        ->and($content->document['content'][0])->toBe(['type' => 'blogImage', 'attrs' => ['imageId' => 7]])
        ->and($content->document['content'][1])->toBe(['type' => 'blogImage', 'attrs' => ['imageId' => 9]])
        ->and($content->document['content'])->toHaveCount(4);
});

it('keeps nested lists and ignores what does not belong in a list item', function () {
    $content = blogNormalizer()->normalize(blogDoc([
        'type' => 'bulletList',
        'attrs' => ['tight' => true],
        'content' => [
            ['type' => 'listItem', 'content' => [
                blogParagraph(blogText('Parent')),
                ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [
                    ['type' => 'listItem', 'content' => [blogParagraph(blogText('Child'))]],
                ]],
            ]],
            ['type' => 'listItem', 'content' => [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [blogText('Ignored heading words')]]]],
            ['type' => 'listItem', 'content' => []],
            blogParagraph(blogText('Not an item')),
        ],
    ]));

    expect($content->document)->toBe(blogDoc([
        'type' => 'bulletList',
        'content' => [
            ['type' => 'listItem', 'content' => [
                blogParagraph(blogText('Parent')),
                ['type' => 'orderedList', 'content' => [
                    ['type' => 'listItem', 'content' => [blogParagraph(blogText('Child'))]],
                ]],
            ]],
        ],
    ]))->and($content->wordCount)->toBe(2);
});

it('counts the words of every kept block, whatever the language', function () {
    $content = blogNormalizer()->normalize(blogDoc(
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [blogText('L\'étiquetage GPSR')]],
        blogParagraph(blogText('Depuis le 13 décembre 2024, '), blogText('tout', [['type' => 'bold']]), blogText(' change : voilà !')),
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [blogParagraph(blogText('¿Qué es?'))]]]],
    ));

    // L'étiquetage GPSR (2) + Depuis le 13 décembre 2024, tout change voilà (8) + Qué es (2) ; ":" et "!" ne comptent pas.
    expect($content->wordCount)->toBe(12);
});

it('refuses a document above the size limit', function () {
    $document = blogDoc(blogParagraph(blogText(str_repeat('mot ', 500))));

    expect(fn () => blogNormalizer(maxBytes: 1_000)->normalize($document))
        ->toThrow(BlogContentException::class, 'too large');
});

it('refuses a document with too many elements', function () {
    $document = blogDoc(...array_fill(0, 30, blogParagraph(blogText('x'))));

    expect(fn () => blogNormalizer(maxNodes: 40)->normalize($document))
        ->toThrow(BlogContentException::class, 'nodes');
});

it('refuses a document nested too deeply', function () {
    $list = blogParagraph(blogText('deep'));

    for ($level = 0; $level < 8; $level++) {
        $list = ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$list]]]];
    }

    expect(fn () => blogNormalizer(maxDepth: 6)->normalize(blogDoc($list)))
        ->toThrow(BlogContentException::class, 'nested');
});
