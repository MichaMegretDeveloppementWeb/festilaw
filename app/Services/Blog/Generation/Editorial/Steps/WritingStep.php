<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\WriterAgent;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationLength;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Exceptions\Blog\BlogEditorialException;
use App\Repositories\Blog\BlogEditorialContextRepository;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogInternalLinkResolver;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;

/**
 * Etape 5 · la redaction de l'article anglais, a partir du seul dossier de recherche. Le code controle ce
 * qu'il recoit : structure, longueurs (le nombre de mots du texte compris), liens (jetons internes connus,
 * liens externes du dossier seulement), emplacements d'images dans la limite reglee, sources du dossier. Un
 * ecart donne lieu a une seule demande de correction. Ce qui reste ensuite est garde au journal ; seul un
 * article vide ou sans source fait echouer l'etape, que le moteur reessaie.
 */
final class WritingStep extends EditorialStep
{
    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogEditorialContextRepository $blog,
        private readonly BlogWritingSignalsInspector $inspector,
        private readonly ArticleLengthRules $lengthRules,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'writing';
    }

    public function timeoutSeconds(): int
    {
        return 600;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $topic = $context->output('topic');
        $research = $context->output('research');

        if (($topic['title'] ?? '') === '' || ($research['facts'] ?? []) === []) {
            throw BlogEditorialException::missingInput($this->key(), 'the topic or the research file');
        }

        $images = GenerationImageOptions::fromPayload($context->payload) ?? GenerationImageOptions::fromSettings($context->settings);
        $maxInline = $images->mode === BlogAiImageMode::None ? 0 : max(0, $images->maxImages - 1);
        $categories = $this->blog->categories();
        $posts = $this->blog->publishedPosts();
        $prompt = implode("\n\n", [
            $this->block('Topic', $topic),
            $this->block('Research file', ['sources' => $research['sources'] ?? [], 'facts' => $research['facts'], 'notes' => $research['notes'] ?? '']),
            $this->block('Instructions from the editor', $context->instructions ?? 'None.'),
            $this->block('Categories', $categories ?: 'No category yet. Set category_id to null.'),
            $this->block('Published articles you can link to (festilaw:post/ID) or suggest as related', $posts ?: 'None yet.'),
            $this->block('Pages you can link to', array_map(static fn (string $key): string => "festilaw:page/{$key}", array_keys(BlogInternalLinkResolver::PAGES))),
            $this->block('Inline images allowed', (string) $maxInline),
        ]);
        $length = $context->length ?? $this->lengthRules->for($context->settings->articleWords);
        $instructions = $this->prompts->compose('festilaw-context', 'editorial-guidelines', 'writing-voice-en')."\n\n".$this->prompts->render('step-writing', [
            'words' => $length->words,
            'min_words' => $length->askedMin,
            'max_words' => $length->askedMax,
        ]);
        $agent = new WriterAgent($instructions, $length->acceptedMax);
        $allowed = [
            'sources' => array_column($research['sources'] ?? [], 'url', 'id'),
            'categories' => array_column($categories, 'id'),
            'posts' => array_column($posts, 'id'),
            'max_inline' => $maxInline,
            'kind' => $topic['kind'] ?? 'evergreen',
            'length' => $context->length,
        ];

        $reply = $this->ask($context, $agent, $prompt);
        $draft = $this->draft($reply->structured(), $allowed);
        $usage = $reply->usage;

        if ($draft['problems'] !== []) {
            $correction = $this->ask($context, $agent, spent: $usage, prompt: $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Fix these problems and answer again in full', '- '.implode("\n- ", $draft['problems'])));
            $usage = $usage->plus($correction->usage);
            $corrected = $this->draft($correction->structured(), $allowed);

            if (! $corrected['article']->isEmpty() && $corrected['source_ids'] !== []) {
                [$draft, $reply] = [$corrected, $correction];
            }
        }

        if ($draft['article']->isEmpty() || $draft['source_ids'] === []) {
            $this->unusable('an article without a title, a body or a source of the research file', $usage);
        }

        return GenerationStepResult::continue(
            [
                'article' => $draft['article']->toArray(),
                'source_ids' => $draft['source_ids'],
                'category_id' => $draft['category_id'],
                'related_post_ids' => $draft['related_post_ids'],
                'featured_image' => $draft['featured_image'],
                'inline_images' => $draft['inline_images'],
                'warnings' => $draft['problems'],
            ],
            $usage,
            $this->report($reply, __('L\'article a été rédigé en anglais à partir du dossier de recherche.')),
        );
    }

    /**
     * La reponse du redacteur, nettoyee, et la liste de ce qui ne respecte pas les consignes.
     *
     * @param  array<string, mixed>  $answer
     * @param  array{sources: array<string, string>, categories: list<int>, posts: list<int>, max_inline: int, kind: string, length: GenerationLength|null}  $allowed
     * @return array{article: EditorialArticle, source_ids: list<string>, category_id: int|null, related_post_ids: list<int>, featured_image: array<string, string>, inline_images: list<array<string, mixed>>, problems: list<string>}
     */
    private function draft(array $answer, array $allowed): array
    {
        $article = EditorialArticle::fromArray($answer);
        $problems = [];

        $sourceIds = array_values(array_intersect(array_filter($this->items($answer, 'source_ids'), is_string(...)), array_keys($allowed['sources'])));
        $categoryId = is_int($answer['category_id'] ?? null) && in_array($answer['category_id'], $allowed['categories'], true) ? $answer['category_id'] : null;
        $related = array_values(array_slice(array_unique(array_intersect(array_filter($this->items($answer, 'related_post_ids'), is_int(...)), $allowed['posts'])), 0, 3));
        $featured = is_array($answer['featured_image'] ?? null) ? array_map(static fn ($value): string => is_string($value) ? trim($value) : '', $answer['featured_image']) : [];
        $inline = array_values(array_filter($this->items($answer, 'inline_images'), static fn ($image): bool => is_array($image) && is_int($image['number'] ?? null)));

        if ($sourceIds === []) {
            $problems[] = 'source_ids must list the ids of the research file sources the article relies on.';
        }

        if (mb_strlen($article->title) > 75) {
            $problems[] = 'The title is longer than 75 characters.';
        }

        if (str_contains($article->title, ':')) {
            $problems[] = 'The title must not be two halves joined by a colon.';
        }

        $descriptionLength = mb_strlen($article->metaDescription);

        if ($descriptionLength < 120 || $descriptionLength > 170) {
            $problems[] = "meta_description has {$descriptionLength} characters, it must have 140 to 160.";
        }

        $headings = preg_match_all('/^##\s/m', $article->body);

        if ($headings < ($allowed['kind'] === 'news' ? 1 : 2)) {
            $problems[] = 'The body needs "##" section headings.';
        }

        if (preg_match('/^#\s/m', $article->body) === 1) {
            $problems[] = 'The body must not contain a "#" heading.';
        }

        $length = $allowed['length'];
        $words = $this->inspector->words($article->body);

        if ($length !== null && $article->body !== '' && ! $length->accepts($words)) {
            $problems[] = "The body has {$words} words. It must have between {$length->askedMin} and {$length->askedMax} words, without padding.";
        }

        $tokens = EditorialMarkdown::linkTokens($article->body);

        if (! in_array('festilaw:page/quiz', $tokens, true) && ! in_array('festilaw:page/pricing', $tokens, true)) {
            $problems[] = 'Link at least once to the quiz (festilaw:page/quiz) or the pricing page (festilaw:page/pricing).';
        }

        foreach ($tokens as $token) {
            [, $type, $key] = explode('/', str_replace(':', '/', $token), 3);

            $known = $type === 'post'
                ? ctype_digit($key) && in_array((int) $key, $allowed['posts'], true)
                : array_key_exists($key, BlogInternalLinkResolver::PAGES);

            if (! $known) {
                $problems[] = "The link token {$token} does not match a published article or a page of the list.";
            }
        }

        preg_match_all('/\]\((https?:[^)\s]+)\)/', $article->body, $external);

        foreach (array_diff($external[1], $allowed['sources']) as $url) {
            $problems[] = "The external link {$url} is not a source of the research file.";
        }

        $placeholders = EditorialMarkdown::imagePlaceholders($article->body);
        $described = array_map(static fn (array $image): int => $image['number'], $inline);

        if (count($placeholders) > $allowed['max_inline']) {
            $problems[] = "The body has more than {$allowed['max_inline']} inline image placeholders.";
        }

        if (array_diff($placeholders, $described) !== [] || count($placeholders) !== count(array_unique($placeholders))) {
            $problems[] = 'Each [[image:N]] placeholder must appear once and be described in inline_images with the same number.';
        }

        if (($featured['description'] ?? '') === '') {
            $problems[] = 'featured_image needs a description.';
        }

        return [
            'article' => $article,
            'source_ids' => $sourceIds,
            'category_id' => $categoryId,
            'related_post_ids' => $related,
            'featured_image' => $featured,
            'inline_images' => array_values(array_filter($inline, static fn (array $image): bool => in_array($image['number'], $placeholders, true))),
            'problems' => array_values(array_unique($problems)),
        ];
    }
}
