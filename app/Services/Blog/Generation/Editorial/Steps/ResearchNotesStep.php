<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use Carbon\CarbonImmutable;

/**
 * Recherche sur le web avant le dossier de faits. Etape a part entiere pour que ses recherches, payees, soient
 * enregistrees avant la mise en forme : un nouvel essai du dossier ne les refait pas (au 17/11, trois essais
 * ont refait huit recherches). Des notes coupees par la limite sont gardees, car elles sont payees et en
 * partie utilisables.
 */
final class ResearchNotesStep extends EditorialStep
{
    /** Decision de Micha du 17/11. */
    private const WEB_SEARCHES = 3;

    /** @param  array{engine?: string, max_results?: int, max_characters?: int}  $webSearch */
    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly array $webSearch = [],
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'research_notes';
    }

    public function timeoutSeconds(): int
    {
        return 400;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $topic = $context->output('topic');

        if (($topic['title'] ?? '') === '') {
            throw BlogEditorialException::missingInput($this->key(), 'the topic');
        }

        $instructions = $this->prompts->compose('festilaw-context', 'editorial-guidelines')."\n\n".$this->prompts->render('step-research-notes', ['searches' => self::WEB_SEARCHES]);
        $reply = $this->ask($context, new WebSearchAgent($instructions, self::WEB_SEARCHES, $this->webSearch), implode("\n\n", [
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Topic', array_intersect_key($topic, array_flip(['kind', 'title', 'angle', 'reader', 'keywords']))),
            $this->block('Instructions from the editor', $context->instructions ?? 'None.'),
            $this->block('Length of the article', 'About '.($context->length->words ?? $context->settings->articleWords).' words in the body.'),
        ]));
        $notes = trim($reply->text());

        if ($notes === '') {
            $this->unusable('no research notes', $reply->usage);
        }

        return GenerationStepResult::continue(
            ['notes' => $notes, 'citations' => $reply->citations(), 'cut_off' => $reply->cutOff],
            $reply->usage,
        );
    }
}
