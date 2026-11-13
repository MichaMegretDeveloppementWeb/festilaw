<?php

use App\Services\Blog\Content\BlogLinkPolicy;

it('accepts web, mail and internal links', function (string $href) {
    expect((new BlogLinkPolicy)->isAllowed($href))->toBeTrue();
})->with([
    'https://eur-lex.europa.eu/eli/reg/2023/988/oj',
    'http://example.com/page?x=1&y=2#part',
    'HTTPS://EXAMPLE.COM',
    'mailto:contact@festilaw.com',
    'mailto:contact@festilaw.com?subject=GPSR',
    '/pricing',
    '/fr/blog/mon-article#section',
]);

it('refuses everything else', function (string $href) {
    expect((new BlogLinkPolicy)->isAllowed($href))->toBeFalse();
})->with([
    'empty' => '',
    'javascript' => 'javascript:alert(1)',
    'javascript in capitals' => 'JaVaScRiPt:alert(1)',
    'javascript hidden behind a tab' => "java\tscript:alert(1)",
    'data uri' => 'data:text/html,<script>alert(1)</script>',
    'protocol relative' => '//evil.example/steal',
    'backslash trick' => '/\\evil.example',
    'no protocol' => 'example.com/page',
    'relative path' => 'pricing',
    'ftp' => 'ftp://example.com/file',
    'leading space' => ' https://example.com',
    'space inside' => 'https://example.com/a b',
    'host missing' => 'https://',
    'mailto without address' => 'mailto:',
    'mailto with markup' => 'mailto:a@b.c"><script>',
    'far too long' => 'https://example.com/'.str_repeat('a', 2100),
]);

it('recognises a link that leaves the site', function (string $href, bool $external) {
    expect((new BlogLinkPolicy('festilaw.com'))->isExternal($href))->toBe($external);
})->with([
    ['https://eur-lex.europa.eu/eli', true],
    ['http://etsy.com', true],
    ['https://festilaw.com/pricing', false],
    ['https://www.festilaw.com/pricing', false],
    ['HTTPS://FESTILAW.COM/', false],
    ['https://festilaw.com.evil.example/', true],
    ['/pricing', false],
    ['mailto:contact@festilaw.com', false],
]);
