<?php

declare(strict_types=1);

use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Actions\Blog\Post\UnpublishBlogPostAction;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use App\Services\Blog\Content\BlogPostHtmlRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BlogContent;

/*
 | Un article depublie ou supprime disparait des liens des autres articles, et leur texte reste (decision de
 | Micha du 20/11) : ses adresses actuelles et anciennes, dans les trois langues, en chemin ou en adresse complete
 | du site. Les liens vers d'autres articles et vers d'autres sites ne bougent pas.
 */

uses(RefreshDatabase::class);

/** Un lien du document : un texte qui porte une marque `link` (et d'autres marques eventuelles). */
function linkedText(string $text, string $href, array $otherMarks = []): array
{
    return ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]], ...$otherMarks]];
}

/** Donne a la version francaise de l'article un paragraphe de liens, et refait son HTML. */
function giveLinks(BlogPost $post, array $nodes): void
{
    BlogPostTranslation::query()->where('blog_post_id', $post->id)->where('locale', 'fr')
        ->update(['content' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $nodes]]])]);
    app(BlogPostHtmlRefresher::class)->refresh($post->fresh()->load(['translations', 'images']));
}

/** @return array{content: array<string, mixed>, html: string} */
function frenchVersion(BlogPost $post): array
{
    $translation = BlogPostTranslation::query()->where('blog_post_id', $post->id)->where('locale', 'fr')->sole();

    return ['content' => $translation->content, 'html' => (string) $translation->content_html];
}

beforeEach(function () {
    $this->target = BlogContent::published('target');
    BlogPostRetiredSlug::query()->create(['blog_post_id' => $this->target->id, 'locale' => 'en', 'slug' => 'old-target']);
    $this->other = BlogContent::published('other');
    $this->source = BlogContent::published('source');
    giveLinks($this->source, [
        linkedText('notre guide', '/fr/blog/target-fr'),
        ['type' => 'text', 'text' => ', '],
        linkedText('le guide complet', rtrim((string) config('app.url'), '/').'/blog/target-en#etapes', [['type' => 'bold']]),
        ['type' => 'text', 'text' => ', '],
        linkedText('son ancienne adresse', '/blog/old-target'),
        ['type' => 'text', 'text' => ', '],
        linkedText('un autre article', '/fr/blog/other-fr'),
        ['type' => 'text', 'text' => ' et '],
        linkedText('Etsy', 'https://www.etsy.com/blog/target-en'),
    ]);
});

it('removes from the other articles every link to an unpublished article, keeping their text', function () {
    app(UnpublishBlogPostAction::class)->execute($this->target->id);

    ['content' => $content, 'html' => $html] = frenchVersion($this->source);
    $nodes = $content['content'][0]['content'];

    expect($nodes[0])->toBe(['type' => 'text', 'text' => 'notre guide'])
        ->and($nodes[2])->toBe(['type' => 'text', 'text' => 'le guide complet', 'marks' => [['type' => 'bold']]])
        ->and($nodes[4])->toBe(['type' => 'text', 'text' => 'son ancienne adresse'])
        ->and($nodes[6]['marks'][0]['attrs']['href'])->toBe('/fr/blog/other-fr')
        ->and($nodes[8]['marks'][0]['attrs']['href'])->toBe('https://www.etsy.com/blog/target-en')
        ->and($html)->toContain('notre guide')->toContain('<strong>le guide complet</strong>')
        ->not->toContain('href="/fr/blog/target-fr"')->not->toContain('/blog/old-target')
        ->toContain('href="/fr/blog/other-fr"')->toContain('https://www.etsy.com/blog/target-en');
});

it('removes them as well when the article is deleted', function () {
    app(DeleteBlogPostAction::class)->execute($this->target->id);

    expect(frenchVersion($this->source)['html'])->not->toContain('href="/fr/blog/target-fr"')
        ->toContain('notre guide')
        ->toContain('href="/fr/blog/other-fr"');
});

it('leaves the other articles alone when the article was already a draft', function () {
    app(UnpublishBlogPostAction::class)->execute($this->target->id);
    giveLinks($this->source, [linkedText('notre guide', '/fr/blog/target-fr')]);

    app(UnpublishBlogPostAction::class)->execute($this->target->id);

    expect(frenchVersion($this->source)['html'])->toContain('href="/fr/blog/target-fr"');
});
