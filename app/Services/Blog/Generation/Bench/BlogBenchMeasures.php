<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Bench;

use App\Data\Blog\Generation\ArticleLengthRules;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;

/**
 * Les mesures automatiques du banc d'essai (cahier des charges § 13), tirees des sorties des etapes, sans
 * aucun appel : longueur a chaque etape, sources et part d'officielles, ecarts releves par la redaction,
 * affirmations retirees par la verification, signes de texte genere encore releves, liens et intertitres.
 * Aussi le bilan d'un banc par configuration, pour la ligne de commande.
 */
final readonly class BlogBenchMeasures
{
    private const TEXT_STEPS = ['writing', 'verification', 'style', 'translation_fr', 'translation_es'];

    public function __construct(
        private BlogWritingSignalsInspector $inspector,
        private ArticleLengthRules $length,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $outputs  sorties du sujet prepare et des etapes de l'article
     * @return array<string, mixed>
     */
    public function article(array $outputs, int $wordsAsked): array
    {
        $sources = array_values(array_filter((array) ($outputs['research']['sources'] ?? []), is_array(...)));
        $types = array_column($sources, 'type', 'id');
        $shown = array_values(array_filter((array) ($outputs['writing']['source_ids'] ?? []), is_string(...)));
        $words = [];

        foreach (self::TEXT_STEPS as $step) {
            $words[$step] = $this->inspector->words((string) ($outputs[$step]['article']['body'] ?? ''));
        }

        $final = (string) ($outputs['style']['article']['body'] ?? '');

        return [
            'words' => $words,
            'length_accepted' => $this->length->for($wordsAsked)->accepts($words['style']),
            'sources' => [
                'research' => count($sources),
                'research_official' => count(array_filter($types, static fn (mixed $type): bool => $type === 'official')),
                'removed_links' => count((array) ($outputs['research']['removed_links'] ?? [])),
                'shown' => count($shown),
                'shown_official' => count(array_filter($shown, static fn (string $id): bool => ($types[$id] ?? null) === 'official')),
                'set_aside' => count((array) ($outputs['writing']['set_aside_source_ids'] ?? [])),
                'extra_reason' => $outputs['writing']['extra_sources_reason'] ?? null,
            ],
            'writing_warnings' => array_values((array) ($outputs['writing']['warnings'] ?? [])),
            'removed_claims' => count((array) ($outputs['verification']['removed_claims'] ?? [])),
            'style_applied' => (bool) ($outputs['style']['style_applied'] ?? false),
            'flagged_sentences' => [
                'en' => count((array) ($outputs['style']['signals']['flagged'] ?? [])),
                'fr' => count((array) ($outputs['translation_fr']['signals']['flagged'] ?? [])),
                'es' => count((array) ($outputs['translation_es']['signals']['flagged'] ?? [])),
            ],
            'second_passes' => [
                'en' => ($outputs['style']['second_pass'] ?? null) !== null,
                'fr' => ($outputs['translation_fr']['second_pass'] ?? null) !== null,
                'es' => ($outputs['translation_es']['second_pass'] ?? null) !== null,
            ],
            'version_corrections' => [
                'fr' => count((array) ($outputs['version_check_fr']['corrections'] ?? [])),
                'es' => count((array) ($outputs['version_check_es']['corrections'] ?? [])),
            ],
            'number_warnings' => [
                'fr' => count((array) ($outputs['translation_fr']['number_warnings'] ?? [])),
                'es' => count((array) ($outputs['translation_es']['number_warnings'] ?? [])),
            ],
            'headings' => preg_match_all('/^## /m', $final),
            'links' => [
                'external' => preg_match_all('/\]\(https?:\/\//', $final),
                'internal' => preg_match_all('/\]\(festilaw:/', $final),
            ],
        ];
    }

    /**
     * Le bilan par configuration : articles ecrits, cout moyen d'un article (sujet prepare compris), duree
     * moyenne de l'ecriture, mots moyens du texte anglais final, affirmations retirees.
     *
     * @param  list<array<string, mixed>>  $records  les articles ecrits (RunBlogBenchArticleAction)
     * @return array<string, array{name: string, articles: int, cost_microusd: int, duration_ms: int, words: int, removed_claims: int}>
     */
    public function summary(array $records): array
    {
        $groups = [];

        foreach ($records as $record) {
            $groups[(string) $record['configuration']['key']][] = $record;
        }

        return array_map(static function (array $group): array {
            $count = count($group);
            $mean = static fn (callable $value): int => intdiv((int) array_sum(array_map($value, $group)), $count);

            return [
                'name' => (string) $group[0]['configuration']['name'],
                'articles' => $count,
                'cost_microusd' => $mean(static fn (array $record): int => (int) $record['cost_microusd'] + (int) $record['preparation_cost_microusd']),
                'duration_ms' => $mean(static fn (array $record): int => (int) $record['duration_ms']),
                'words' => $mean(static fn (array $record): int => (int) $record['measures']['words']['style']),
                'removed_claims' => (int) array_sum(array_map(static fn (array $record): int => (int) $record['measures']['removed_claims'], $group)),
            ];
        }, $groups);
    }
}
