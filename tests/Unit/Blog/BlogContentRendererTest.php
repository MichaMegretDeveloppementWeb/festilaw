<?php

use App\Models\BlogImage;
use App\Services\Blog\Content\BlogContentRenderer;
use App\Services\Blog\Content\BlogLinkPolicy;
use App\Services\Blog\Image\BlogImagePaths;
use App\Services\Blog\Slug\BlogSlugGenerator;

function blogRenderer(): BlogContentRenderer
{
    return new BlogContentRenderer(
        new BlogImagePaths('blog'),
        new BlogSlugGenerator,
        new BlogLinkPolicy('festilaw.com'),
        '(min-width: 800px) 760px, 100vw',
        ['medium' => 560, 'small' => 360],
    );
}

/** @param array<int, array<string, mixed>> $blocks */
function renderBlogBlocks(array $blocks, string $locale = 'en', array $images = []): string
{
    return blogRenderer()->render(['type' => 'doc', 'content' => $blocks], $locale, $images);
}

/** @param list<int> $widths */
function blogImageForRender(int $id, array $widths = [480, 768, 1200, 1600]): BlogImage
{
    $image = new BlogImage([
        'uuid' => '11111111-2222-3333-4444-555555555555',
        'basename' => 'ce-label',
        'variants' => ['widths' => $widths, 'share' => false],
        'width' => 1600,
        'height' => 900,
        'alt' => ['en' => 'A "CE" label', 'fr' => 'Une étiquette CE'],
    ]);
    $image->id = $id;

    return $image;
}

it('renders nothing for an empty document', function () {
    expect(blogRenderer()->render(null, 'en'))->toBe('');
});

it('escapes every piece of text', function () {
    $html = renderBlogBlocks([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert("x")</script> & co']]],
    ]);

    expect($html)->toBe('<p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; co</p>');
});

it('renders marks with the link outside, and line breaks', function () {
    $html = renderBlogBlocks([
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Read ', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => 'the rule', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/pricing']], ['type' => 'bold'], ['type' => 'italic']]],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'now'],
        ]],
    ]);

    expect($html)->toBe('<p><em>Read </em><a href="/pricing"><strong><em>the rule</em></strong></a><br>now</p>');
});

it('opens external links in a new tab and leaves internal ones alone', function () {
    $link = fn (string $href): array => ['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]],
    ]];

    expect(renderBlogBlocks([$link('https://eur-lex.europa.eu/a?b=1&c=2')]))
        ->toBe('<p><a href="https://eur-lex.europa.eu/a?b=1&amp;c=2" target="_blank" rel="noopener">x</a></p>')
        ->and(renderBlogBlocks([$link('https://www.festilaw.com/pricing')]))->toBe('<p><a href="https://www.festilaw.com/pricing">x</a></p>')
        ->and(renderBlogBlocks([$link('mailto:contact@festilaw.com')]))->toBe('<p><a href="mailto:contact@festilaw.com">x</a></p>');
});

it('gives each heading an anchor taken from its text, unique in the page', function () {
    $heading = fn (int $level, string $text): array => ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];

    $html = renderBlogBlocks([
        $heading(2, 'L\'étiquetage & le marquage'),
        $heading(3, 'Exemples'),
        $heading(3, 'Exemples'),
        $heading(2, '¿?'),
    ]);

    expect($html)->toBe(implode("\n", [
        '<h2 id="l-etiquetage-le-marquage">L&apos;étiquetage &amp; le marquage</h2>',
        '<h3 id="exemples">Exemples</h3>',
        '<h3 id="exemples-2">Exemples</h3>',
        '<h2 id="section">¿?</h2>',
    ]));
});

it('renders simple list items without paragraph tags, nested lists included', function () {
    $item = fn (array ...$content): array => ['type' => 'listItem', 'content' => $content];
    $paragraph = fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];

    $html = renderBlogBlocks([
        ['type' => 'bulletList', 'content' => [
            $item($paragraph('One')),
            $item($paragraph('Two'), ['type' => 'orderedList', 'content' => [$item($paragraph('Two A'))]]),
            $item($paragraph('First part'), $paragraph('Second part')),
        ]],
    ]);

    expect($html)->toBe('<ul><li>One</li><li>Two<ol><li>Two A</li></ol></li><li><p>First part</p><p>Second part</p></li></ul>');
});

it('renders a quotation', function () {
    $html = renderBlogBlocks([
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Safety first.']]]]],
    ]);

    expect($html)->toBe('<blockquote><p>Safety first.</p></blockquote>');
});

it('marks a reduced image and tells the browser how wide it is shown, so that a lighter file is loaded', function (string $width, string $sizes) {
    $html = renderBlogBlocks([['type' => 'blogImage', 'attrs' => ['imageId' => 7, 'width' => $width]]], 'en', [blogImageForRender(7)]);

    expect($html)->toStartWith("<figure class=\"blog-figure blog-figure--{$width}\"><img")
        ->toContain(" sizes=\"{$sizes}\"")
        // Les memes variantes restent proposees : c'est le navigateur qui choisit.
        ->toContain('-480.webp 480w')
        ->toContain('-1600.webp 1600w');
})->with([
    'medium' => ['medium', '(min-width: 560px) 560px, 100vw'],
    'small' => ['small', '(min-width: 360px) 360px, 100vw'],
]);

it('renders an image whose width is not a known reduced width at full width', function () {
    $html = renderBlogBlocks([['type' => 'blogImage', 'attrs' => ['imageId' => 7, 'width' => 'huge']]], 'en', [blogImageForRender(7)]);

    expect($html)->toStartWith('<figure class="blog-figure"><img')
        ->toContain(' sizes="(min-width: 800px) 760px, 100vw"');
});

it('renders a responsive figure with the description of the page language', function () {
    $html = renderBlogBlocks([['type' => 'blogImage', 'attrs' => ['imageId' => 7]]], 'en', [blogImageForRender(7)]);
    $base = '/storage/blog/11111111-2222-3333-4444-555555555555/ce-label';

    expect($html)->toBe(
        '<figure class="blog-figure"><img'
        ." src=\"{$base}-1200.webp\""
        ." srcset=\"{$base}-480.webp 480w, {$base}-768.webp 768w, {$base}-1200.webp 1200w, {$base}-1600.webp 1600w\""
        .' sizes="(min-width: 800px) 760px, 100vw" width="1600" height="900"'
        .' alt="A &quot;CE&quot; label" loading="lazy" decoding="async"></figure>'
    );
});

it('uses the largest variant of a small image and the description of another language', function () {
    $html = renderBlogBlocks([['type' => 'blogImage', 'attrs' => ['imageId' => 7]]], 'fr', [blogImageForRender(7, [480, 768])]);

    expect($html)->toContain('src="/storage/blog/11111111-2222-3333-4444-555555555555/ce-label-768.webp"')
        ->toContain('alt="Une étiquette CE"')
        ->not->toContain('1200w');
});

it('renders an empty description rather than a missing one, and skips an unknown image', function () {
    $blocks = [
        ['type' => 'blogImage', 'attrs' => ['imageId' => 7]],
        ['type' => 'blogImage', 'attrs' => ['imageId' => 404]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'After']]],
    ];

    $html = renderBlogBlocks($blocks, 'es', [blogImageForRender(7)]);

    expect($html)->toContain('alt=""')
        ->and(substr_count($html, '<figure'))->toBe(1)
        ->and($html)->toEndWith("\n<p>After</p>");
});
