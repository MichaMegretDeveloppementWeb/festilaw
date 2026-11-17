<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\StyleAgent;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogFactGuard;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;

/**
 * Etape 7 · la relecture du style (annexe A.12, tache 6.18). L'agent recrit les tournures d'un texte genere
 * sans toucher a un fait. Le code compare chaque version au texte verifie : si un nombre, un lien, un jeton
 * ou un emplacement d'image a change, la version est ecartee.
 *
 * Puis le compteur de signes releve les phrases qui en portent encore (tache 6.19). Au-dela du seuil
 * (config blog.ai.style.max_flagged_sentences), ces phrases sont renvoyees au modele pour un second passage,
 * un seul, sous la meme garde des faits. Decision du 17/11 : les signes etaient comptes sans servir.
 */
final class StyleStep extends EditorialStep
{
    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
        private readonly BlogWritingSignalsInspector $signals,
        private readonly int $maxFlaggedSentences,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'style';
    }

    public function timeoutSeconds(): int
    {
        return 400;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $checked = EditorialArticle::fromArray($context->output('verification')['article'] ?? []);

        if ($checked->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the checked article');
        }

        $agent = new StyleAgent($this->prompts->compose('festilaw-context', 'editorial-guidelines', 'writing-voice-en', 'step-style'));
        $reply = $this->ask($context, $agent, $this->block('Article to edit', $checked->forPrompt()));
        $styled = EditorialArticle::fromArray($reply->structured());
        $usage = $reply->usage;

        $differences = $this->differences($checked, $styled);
        $applied = $differences === [];
        $final = $applied ? $styled : $checked;
        $signs = $this->prompts->lines('signals-en');
        $signals = $this->signals->inspect($this->checkedText($final), $signs);
        $secondPass = null;

        if (count($signals['flagged']) > $this->maxFlaggedSentences) {
            $second = $this->ask($context, $agent, spent: $usage, prompt: $this->block('Article to edit', $final->forPrompt())
                ."\n\n".$this->block('Sentences that still show signs of generated text', implode("\n", array_map(
                    static fn (array $flag): string => '- '.$flag['sentence'].' ('.implode(', ', $flag['signs']).')',
                    $signals['flagged'],
                ))));
            $usage = $usage->plus($second->usage);
            $rewritten = EditorialArticle::fromArray($second->structured());
            $secondDifferences = $this->differences($checked, $rewritten);
            $secondPass = ['flagged_before' => count($signals['flagged']), 'applied' => $secondDifferences === [], 'differences' => $secondDifferences];

            if ($secondDifferences === []) {
                $final = $rewritten;
                $applied = true;
                $signals = $this->signals->inspect($this->checkedText($final), $signs);
            }
        }

        return GenerationStepResult::continue(
            [
                'article' => $final->toArray(),
                'style_applied' => $applied,
                'differences' => $differences,
                'second_pass' => $secondPass,
                'signals' => $signals,
            ],
            $usage,
            $applied
                ? $this->report($reply, __('Le style a été relu pour retirer les tournures d\'un texte généré.'))
                : __('La relecture du style a été écartée, car elle modifiait un chiffre ou un lien. Le texte vérifié est gardé.'),
        );
    }

    /** Le texte que le controle des signes lit : le titre, comme un intertitre, puis le corps. */
    private function checkedText(EditorialArticle $article): string
    {
        return '# '.$article->title.'

'.$article->body;
    }

    /** @return list<string> ce qui distingue une version du texte verifie (vide : aucun fait n'a bouge) */
    private function differences(EditorialArticle $checked, EditorialArticle $version): array
    {
        return $version->isEmpty() ? ['the style edit came back empty'] : $this->guard->differences($checked->text(), $version->text());
    }
}
