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
use Carbon\CarbonImmutable;

/**
 * Etape 5 · la redaction de l'article anglais, a partir du seul dossier de recherche. Le code controle ce
 * qu'il recoit : structure, longueurs (le nombre de mots du texte compris), liens (jetons internes connus,
 * liens externes du dossier seulement), emplacements d'images dans la limite reglee, sources du dossier. Un
 * ecart donne lieu a une seule demande de correction. Ce qui reste ensuite est garde au journal ; seul un
 * article vide ou sans source fait echouer l'etape, que le moteur reessaie.
 *
 * Les sources citees (`source_ids`) sont celles affichees sous l'article : trois au plus, cinq quand le
 * redacteur dit pourquoi (decision de Micha du 18/11, les articles 77 a 80 en affichaient jusqu'a 13). Au-dela,
 * l'etape garde les mieux classees sans nouvel appel, et note les autres au journal.
 */
final class WritingStep extends EditorialStep
{
    private const SOURCE_RANKS = ['official' => 0, 'professional' => 1, 'forum' => 2];

    /** L'appui d'une affirmation tiree de la fiche des regles de l'UE, qui n'a pas d'identifiant de fait. */
    public const RULES_BASIS = 'RULES';

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogEditorialContextRepository $blog,
        private readonly BlogWritingSignalsInspector $inspector,
        private readonly ArticleLengthRules $lengthRules,
        private readonly int $maxSources = 3,
        private readonly int $maxJustifiedSources = 5,
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
            // La date du jour situe les regles datees de la reference (controle du 20/11).
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Topic', $topic),
            $this->block('Research file', ['sources' => $research['sources'] ?? [], 'facts' => $research['facts'], 'notes' => $research['notes'] ?? '']),
            $this->block('Instructions from the editor', $context->instructions ?? 'None.'),
            $this->block('Categories', $categories ?: 'No category yet. Set category_id to null.'),
            $this->block('Published articles you can link to (festilaw:post/ID) or suggest as related', $posts ?: 'None yet.'),
            $this->block('Pages you can link to', array_map(static fn (string $key): string => "festilaw:page/{$key}", array_keys(BlogInternalLinkResolver::PAGES))),
            $this->block('Inline images allowed', (string) $maxInline),
        ]);
        $length = $context->length ?? $this->lengthRules->for($context->settings->articleWords);
        $instructions = $this->prompts->compose('festilaw-context', 'eu-product-rules', 'editorial-guidelines', 'writing-voice-en')."\n\n".$this->prompts->render('step-writing', [
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
            'facts' => array_values(array_filter(array_column(array_filter($research['facts'] ?? [], is_array(...)), 'id'), is_string(...))),
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

        $reason = $this->text($reply->structured(), 'extra_sources_reason');
        [$shown, $setAside] = $this->shownSources($draft['source_ids'], $research, $reason);

        return GenerationStepResult::continue(
            [
                'article' => $draft['article']->toArray(),
                'source_ids' => $shown,
                'set_aside_source_ids' => $setAside,
                'extra_sources_reason' => $reason === '' ? null : $reason,
                'claims' => $draft['claims'],
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
     * Les sources affichees sous l'article, dans l'ordre ou le redacteur les a donnees, et celles ecartees. Au-dela
     * du nombre permis, l'etape garde les officielles, puis les professionnelles, puis les forums, et a rang
     * egal celles qui portent le plus de faits du dossier.
     *
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $research
     * @return array{0: list<string>, 1: list<string>}
     */
    private function shownSources(array $ids, array $research, string $reason): array
    {
        $allowed = $reason === '' ? $this->maxSources : $this->maxJustifiedSources;

        if (count($ids) <= $allowed) {
            return [$ids, []];
        }

        $types = array_column($research['sources'] ?? [], 'type', 'id');
        $facts = array_count_values(array_merge(...array_map(
            static fn (array $fact): array => array_values(array_filter((array) ($fact['source_ids'] ?? []), is_string(...))),
            array_values(array_filter($research['facts'] ?? [], is_array(...))),
        )));
        $ranked = $ids;
        usort($ranked, static fn (string $a, string $b): int => [self::SOURCE_RANKS[$types[$a] ?? ''] ?? 3, -($facts[$a] ?? 0), array_search($a, $ids, true)]
            <=> [self::SOURCE_RANKS[$types[$b] ?? ''] ?? 3, -($facts[$b] ?? 0), array_search($b, $ids, true)]);
        $kept = array_slice($ranked, 0, $allowed);

        return [
            array_values(array_filter($ids, static fn (string $id): bool => in_array($id, $kept, true))),
            array_values(array_filter($ids, static fn (string $id): bool => ! in_array($id, $kept, true))),
        ];
    }

    /**
     * La reponse du redacteur, nettoyee, et la liste de ce qui ne respecte pas les consignes.
     *
     * @param  array<string, mixed>  $answer
     * @param  array{sources: array<string, string>, categories: list<int>, posts: list<int>, max_inline: int, kind: string, length: GenerationLength|null, facts: list<string>}  $allowed
     * @return array{article: EditorialArticle, source_ids: list<string>, claims: list<array{statement: string, basis: list<string>}>, category_id: int|null, related_post_ids: list<int>, featured_image: array<string, string>, inline_images: list<array<string, mixed>>, problems: list<string>}
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
            $problems[] = $words > $length->acceptedMax
                ? "The body has {$words} words. Shorten it to between {$length->askedMin} and {$length->askedMax} words."
                : "The body has {$words} words, below the {$length->askedMin} asked. Use material of the research file or the reference you have not used yet. If there is none, keep the article as it is: a shorter article is fine, an invented point is not.";
        }

        [$claims, $claimProblems] = $this->claims($answer, $allowed['facts']);
        array_push($problems, ...$claimProblems);

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
            'claims' => $claims,
            'category_id' => $categoryId,
            'related_post_ids' => $related,
            'featured_image' => $featured,
            'inline_images' => array_values(array_filter($inline, static fn (array $image): bool => in_array($image['number'], $placeholders, true))),
            'problems' => array_values(array_unique($problems)),
        ];
    }

    /**
     * Les affirmations du redacteur et leurs appuis : des faits du dossier, ou RULES pour la fiche des regles de
     * l'UE. Une affirmation sans appui, ou qui cite un fait absent du dossier, lui est renvoyee (banc d'essai du
     * 18/11 : les erreurs graves venaient de regles ajoutees pour completer un exemple ou une liste).
     *
     * @param  array<string, mixed>  $answer
     * @param  list<string>  $facts  les identifiants des faits du dossier
     * @return array{0: list<array{statement: string, basis: list<string>}>, 1: list<string>}
     */
    private function claims(array $answer, array $facts): array
    {
        $claims = [];
        $problems = [];

        foreach ($this->items($answer, 'claims') as $claim) {
            if (! is_array($claim) || $this->text($claim, 'statement') === '') {
                continue;
            }

            $statement = $this->text($claim, 'statement');
            $basis = array_values(array_unique(array_filter(array_map(static fn ($id): string => is_string($id) ? trim($id) : '', $this->items($claim, 'basis')))));
            $unknown = array_values(array_diff($basis, [...$facts, self::RULES_BASIS]));
            $quoted = mb_strimwidth($statement, 0, 160, '...');

            if ($basis === []) {
                $problems[] = "The statement \"{$quoted}\" has no basis. Give the ids of the facts that support it, or RULES, or remove it from the article.";
            } elseif ($unknown !== []) {
                $problems[] = "The statement \"{$quoted}\" cites ".implode(', ', $unknown).', which the research file does not contain.';
            }

            $claims[] = ['statement' => $statement, 'basis' => $basis];
        }

        if ($claims === []) {
            $problems[] = 'claims must list every legal or factual statement of the article, each with its basis.';
        }

        return [$claims, $problems];
    }
}
