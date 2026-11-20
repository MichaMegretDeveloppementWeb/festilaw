<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\BlogEffortLevels;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use Carbon\CarbonImmutable;

/**
 * Recherche des preuves, entre la redaction et la verification (demande de Micha du 20/11). Le verificateur ne
 * relisait l'article que contre le dossier de faits : une source fausse ou mal comprise de la recherche passait.
 * Cette etape cherche sur le web ce que disent les textes officiels des affirmations les plus risquees de
 * l'article, et le verificateur s'en sert. Etape a part, comme la recherche sur le web, pour que ses recherches,
 * payees, ne soient pas refaites par un nouvel essai de la verification. Le nombre de recherches vient du niveau
 * d'effort ; a zero, l'etape passe sans appel.
 */
final class FactSearchStep extends EditorialStep
{
    /** @param  array{engine?: string, max_results?: int, max_characters?: int}  $webSearch */
    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogEffortLevels $effort,
        private readonly array $webSearch = [],
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'fact_search';
    }

    public function timeoutSeconds(): int
    {
        return 400;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $written = EditorialArticle::fromArray($context->output('writing')['article'] ?? []);

        if ($written->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the written article');
        }

        $searches = $this->effort->searches($context->effort, $this->key());

        if ($searches === 0) {
            return GenerationStepResult::continue(['skipped' => true, 'notes' => '', 'citations' => [], 'cut_off' => false]);
        }

        $research = $context->output('research');
        $claims = (array) ($context->output('writing')['claims'] ?? []);
        $instructions = $this->prompts->compose('festilaw-context')."\n\n".$this->prompts->render('step-fact-search', ['searches' => $searches]);
        $reply = $this->ask($context, new WebSearchAgent($instructions, $searches, $this->webSearch), implode("\n\n", array_filter([
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Sources of the research file', $research['sources'] ?? []),
            $claims === [] ? null : $this->block('Statements of the article and their basis, as the writer listed them (RULES: the reference of EU product rules)', $claims),
            $this->block('Article', $written->forPrompt()),
        ])));
        $notes = trim($reply->text());

        if ($notes === '') {
            $this->unusable('no evidence notes', $reply->usage);
        }

        return GenerationStepResult::continue(
            ['skipped' => false, 'notes' => $notes, 'citations' => $reply->citations(), 'cut_off' => $reply->cutOff],
            $reply->usage,
        );
    }
}
