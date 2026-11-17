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
 * Etape 5 · la relecture du style (annexe A.12, tache 6.18). L'agent recrit les tournures d'un texte genere
 * sans toucher a un fait. Le code compare les deux versions : si un nombre, un lien, un jeton ou un
 * emplacement d'image a change, la passe est ecartee et le texte verifie est garde. Puis le compteur de
 * signes mesure le texte retenu (tache 6.19), pour le journal et le banc d'essai.
 */
final class StyleStep extends EditorialStep
{
    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
        private readonly BlogWritingSignalsInspector $signals,
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

        $differences = $styled->isEmpty() ? ['the style edit came back empty'] : $this->guard->differences($checked->text(), $styled->text());
        $applied = $differences === [];
        $final = $applied ? $styled : $checked;

        return GenerationStepResult::continue(
            [
                'article' => $final->toArray(),
                'style_applied' => $applied,
                'differences' => $differences,
                'signals' => $this->signals->inspect($final->body, $this->prompts->lines('signals-en')),
            ],
            $reply->usage,
            $applied
                ? $this->report($reply, __('Le style a été relu pour retirer les tournures d\'un texte généré.'))
                : __('La relecture du style a été écartée, car elle modifiait un chiffre ou un lien. Le texte vérifié est gardé.'),
        );
    }
}
