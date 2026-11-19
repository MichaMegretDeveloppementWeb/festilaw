<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use Carbon\CarbonImmutable;

/**
 * Recherche de pistes avant le choix du sujet : les changements recents, et les regles anciennes que les
 * vendeurs comprennent encore mal (decision de Micha du 19/11 : la veille ne contraint pas a l'actualite).
 * Etape a part entiere pour que ses recherches, payees, soient enregistrees avant le choix : un nouvel essai
 * du choix ne les refait pas. Un sujet impose s'en passe, la recherche de l'article cherchera de toute facon
 * sur le web.
 */
final class TopicScanStep extends EditorialStep
{
    /** Decision de Micha du 17/11. */
    private const WEB_SEARCHES = 2;

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
        return 'topic_scan';
    }

    public function timeoutSeconds(): int
    {
        return 300;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        if (filled($context->topicHint)) {
            return GenerationStepResult::continue(['skipped' => true, 'notes' => '', 'citations' => [], 'cut_off' => false]);
        }

        $instructions = $this->prompts->compose('festilaw-context')."\n\n".$this->prompts->render('step-topic-scan', ['searches' => self::WEB_SEARCHES]);
        $reply = $this->ask($context, new WebSearchAgent($instructions, self::WEB_SEARCHES, $this->webSearch), implode("\n\n", [
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Instructions from the editor', $context->instructions ?? 'None.'),
        ]));

        return GenerationStepResult::continue(
            ['skipped' => false, 'notes' => trim($reply->text()), 'citations' => $reply->citations(), 'cut_off' => $reply->cutOff],
            $reply->usage,
        );
    }
}
