<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\ResearchAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\Sources\SourceLinkChecker;
use Carbon\CarbonImmutable;

/**
 * Etape 2 · la recherche. L'agent constitue le dossier de faits du sujet. Chaque lien de source est ensuite
 * teste : un lien mort sort du dossier, avec les faits qui ne reposaient que sur lui (cahier des charges,
 * § 11). Sans source officielle ou professionnelle restante, ou sans fait, la generation s'arrete : un
 * article sans sources fiables ne s'ecrit pas.
 */
final class ResearchStep extends EditorialStep
{
    private const SOURCE_TYPES = ['official', 'professional', 'forum'];

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly SourceLinkChecker $links,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'research';
    }

    public function timeoutSeconds(): int
    {
        return 600;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $topic = $context->output('topic');

        if (($topic['title'] ?? '') === '') {
            throw BlogEditorialException::missingInput($this->key(), 'the topic');
        }

        $agent = new ResearchAgent($this->prompts->compose('festilaw-context', 'editorial-guidelines', 'step-research'));
        $reply = $this->ask($context, $agent, implode("\n\n", [
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Topic', array_intersect_key($topic, array_flip(['kind', 'title', 'angle', 'reader', 'keywords']))),
            $this->block('Instructions from the editor', $context->instructions ?? 'None.'),
        ]));

        $answer = $reply->structured();
        $sources = $this->sources($answer);
        $facts = $this->facts($answer);

        if ($sources === [] && $facts === []) {
            throw BlogEditorialException::unusableAnswer($this->key(), 'no sources and no facts');
        }

        $alive = $this->links->check(array_column($sources, 'url'));
        $removed = array_values(array_filter($sources, static fn (array $source): bool => ! ($alive[$source['url']] ?? false)));
        $sources = array_values(array_filter($sources, static fn (array $source): bool => $alive[$source['url']] ?? false));
        $kept = array_column($sources, 'id');
        $facts = array_values(array_filter(array_map(
            static fn (array $fact): array => [...$fact, 'source_ids' => array_values(array_intersect($fact['source_ids'], $kept))],
            $facts,
        ), static fn (array $fact): bool => $fact['source_ids'] !== []));

        $reliable = array_filter($sources, static fn (array $source): bool => $source['type'] !== 'forum');
        $output = [
            'sources' => $sources,
            'facts' => $facts,
            'notes' => $this->text($answer, 'notes'),
            'removed_links' => array_column($removed, 'url'),
            'citations' => $reply->citations(),
        ];

        if ($reliable === [] || $facts === []) {
            return GenerationStepResult::stop(
                BlogGenerationOutcomeReason::InsufficientSources,
                $output,
                $reply->usage,
                __('Les sources trouvées n\'étaient pas assez fiables pour écrire sur ce sujet, donc aucun article n\'a été écrit.'),
            );
        }

        $official = count(array_filter($sources, static fn (array $source): bool => $source['type'] === 'official'));

        return GenerationStepResult::continue(
            $output,
            $reply->usage,
            $this->report($reply, trans_choice(
                '{0} :count sources consultées, aucune officielle.|{1} :count sources consultées, dont une officielle.|[2,*] :count sources consultées, dont :official officielles.',
                $official,
                ['count' => count($sources), 'official' => $official],
            )),
        );
    }

    /**
     * Les sources rendues, chacune avec un identifiant, une adresse web et un type connu.
     *
     * @param  array<string, mixed>  $answer
     * @return list<array{id: string, title: string, url: string, publisher: string, type: string, date: string|null}>
     */
    private function sources(array $answer): array
    {
        $sources = [];

        foreach ($this->items($answer, 'sources') as $source) {
            if (! is_array($source)) {
                continue;
            }

            $id = $this->text($source, 'id');
            $url = $this->text($source, 'url');
            $type = $this->text($source, 'type');

            if ($id === '' || ! preg_match('#^https?://#i', $url) || ! in_array($type, self::SOURCE_TYPES, true) || isset($sources[$id])) {
                continue;
            }

            $sources[$id] = [
                'id' => $id,
                'title' => $this->text($source, 'title'),
                'url' => $url,
                'publisher' => $this->text($source, 'publisher'),
                'type' => $type,
                'date' => $this->text($source, 'date') ?: null,
            ];
        }

        return array_values($sources);
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return list<array{id: string, claim: string, source_ids: list<string>, date: string|null}>
     */
    private function facts(array $answer): array
    {
        $facts = [];

        foreach ($this->items($answer, 'facts') as $fact) {
            if (! is_array($fact) || $this->text($fact, 'claim') === '') {
                continue;
            }

            $facts[] = [
                'id' => $this->text($fact, 'id') ?: 'F'.(count($facts) + 1),
                'claim' => $this->text($fact, 'claim'),
                'source_ids' => array_values(array_filter($this->items($fact, 'source_ids'), is_string(...))),
                'date' => $this->text($fact, 'date') ?: null,
            ];
        }

        return $facts;
    }
}
