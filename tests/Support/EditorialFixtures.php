<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Blog\Generation\HostResolver;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Services\Blog\Generation\BlogAiSettingsService;

/**
 * Donnees d'exemple du pipeline editorial : le contexte d'une etape, et ce que rendent les etapes et les
 * agents quand tout va bien. Un test part de la et change ce qu'il eprouve.
 */
final class EditorialFixtures
{
    /**
     * Par defaut, la generation demande des photos de banque, deux images en tout (une dans le texte).
     *
     * @param  array<string, array<string, mixed>>  $outputs
     * @param  array<string, mixed>|null  $payload
     */
    public static function context(array $outputs = [], ?string $topicHint = null, ?string $instructions = null, ?array $payload = null, ?int $budgetLeft = null): GenerationStepContext
    {
        $payload ??= ['images' => ['mode' => 'stock', 'max' => 2]];

        return new GenerationStepContext(
            generationId: 999_999,
            type: BlogGenerationType::Article,
            topicHint: $topicHint,
            instructions: $instructions,
            settings: app(BlogAiSettingsService::class)->current(),
            outputs: $outputs,
            payload: $payload,
            attempt: 1,
            budgetLeftMicrousd: $budgetLeft,
        );
    }

    /** Toute adresse se resout en une adresse publique (aucun DNS reel). */
    public static function publicHosts(): void
    {
        app()->instance(HostResolver::class, new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    /**
     * Les notes de l'etape « recherche sur le web », qui citent les adresses des sources donnees (une source
     * dont l'adresse n'est pas dans les notes est ecartee par l'etape).
     *
     * @param  list<array<string, mixed>>|null  $sources
     */
    public static function notesCiting(?array $sources = null): string
    {
        $sources ??= self::research()['sources'];

        return "Sources found:\n".implode("\n", array_map(static fn (array $source): string => '- '.$source['title'].' ('.$source['url'].')', $sources));
    }

    /**
     * Ce que l'etape « recherche sur le web » enregistre.
     *
     * @param  list<array<string, mixed>>|null  $sources
     * @return array<string, mixed>
     */
    public static function researchNotes(?array $sources = null, ?string $notes = null): array
    {
        return ['notes' => $notes ?? self::notesCiting($sources), 'citations' => [], 'cut_off' => false];
    }

    /** @return array<string, mixed> */
    public static function topic(): array
    {
        return [
            'decision' => 'topic',
            'kind' => 'evergreen',
            'title' => 'Adding an economic operator on Etsy',
            'angle' => 'What UK sellers must fill in on Etsy since the GPSR.',
            'reader' => 'UK makers selling on Etsy',
            'keywords' => ['etsy economic operator', 'gpsr etsy'],
            'reason' => 'Sellers ask about it every week.',
        ];
    }

    /** @return array<string, mixed> */
    public static function research(): array
    {
        return [
            'sources' => [
                ['id' => 'S1', 'title' => 'GPSR', 'url' => 'https://eur-lex.europa.eu/eli/reg/2023/988/oj', 'publisher' => 'EUR-Lex', 'type' => 'official', 'date' => '2023-05-23'],
                ['id' => 'S2', 'title' => 'Etsy help', 'url' => 'https://help.etsy.com/hc/en-gb/articles/economic-operator', 'publisher' => 'Etsy', 'type' => 'official', 'date' => null],
            ],
            'facts' => [
                ['id' => 'F1', 'claim' => 'The GPSR applies since 13 December 2024.', 'source_ids' => ['S1'], 'date' => '2024-12-13'],
                ['id' => 'F2', 'claim' => 'Etsy asks for an economic operator in the listing settings.', 'source_ids' => ['S2'], 'date' => null],
            ],
            'notes' => '',
        ];
    }

    /** Le corps d'un article qui respecte les consignes. */
    public static function body(): string
    {
        return "Since 13 December 2024, every product you sell to the EU needs an economic operator. [Read the regulation](https://eur-lex.europa.eu/eli/reg/2023/988/oj).\n\n"
            ."## What Etsy asks for\n\nEtsy asks for it in the listing settings. [Check your case](festilaw:page/quiz).\n\n[[image:1]]\n\n"
            ."## What to do next\n\nGather the details before you edit your listings. [See our prices](festilaw:page/pricing).";
    }

    /** @return array<string, mixed> */
    public static function article(array $overrides = []): array
    {
        return [
            'title' => 'Adding an economic operator on Etsy',
            'excerpt' => 'Etsy now asks for an economic operator. Here is what UK sellers fill in.',
            'meta_title' => 'Etsy economic operator for UK sellers',
            'meta_description' => str_repeat('Etsy asks UK sellers for an EU economic operator since the GPSR. What to fill in and where. ', 2),
            'body_markdown' => self::body(),
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    public static function writerAnswer(array $overrides = []): array
    {
        return [
            ...self::article(),
            'meta_description' => 'Etsy asks UK sellers for an EU economic operator since the GPSR came into force. Here is what to fill in, where, and what to prepare.',
            'source_ids' => ['S1', 'S2'],
            'category_id' => null,
            'related_post_ids' => [],
            'featured_image' => ['description' => 'A parcel on a workbench', 'search_query' => 'parcel workbench', 'generation_prompt' => 'A parcel on a wooden workbench.'],
            'inline_images' => [['number' => 1, 'description' => 'A label on a box', 'search_query' => 'shipping label', 'generation_prompt' => 'A shipping label on a box.']],
            'report_fr' => 'L\'article explique le champ demandé par Etsy et finit sur ce que le vendeur prépare.',
            ...$overrides,
        ];
    }

    /** La sortie de la redaction, telle que la garde le journal. @return array<string, mixed> */
    public static function writingOutput(): array
    {
        return [
            'article' => ['title' => self::article()['title'], 'excerpt' => self::article()['excerpt'], 'meta_title' => self::article()['meta_title'], 'meta_description' => self::writerAnswer()['meta_description'], 'body' => self::body()],
            'source_ids' => ['S1', 'S2'],
            'featured_image' => self::writerAnswer()['featured_image'],
            'inline_images' => self::writerAnswer()['inline_images'],
        ];
    }

    /** Un article sous la forme du journal (verification, style). @return array{article: array<string, string>} */
    public static function articleOutput(?string $body = null): array
    {
        return ['article' => [...self::writingOutput()['article'], 'body' => $body ?? self::body()]];
    }
}
