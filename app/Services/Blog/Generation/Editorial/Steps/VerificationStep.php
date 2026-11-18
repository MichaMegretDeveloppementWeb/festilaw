<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogFactGuard;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;

/**
 * Etape 6 · la verification. L'agent relit l'article contre le dossier et la fiche des regles de l'UE, et
 * retire toute affirmation que ni l'un ni l'autre n'etaye. Il ne doit rien ajouter : une version qui apporte
 * un chiffre, un lien ou un jeton absent du texte de depart, ou qui perd un emplacement d'image, lui est
 * renvoyee une fois avec ce qu'elle ajoute, puis refusee. Un renvoi cible coute moins qu'un nouvel essai a
 * l'aveugle, qui refaisait la meme faute (generation 79 du 18/11, numeros d'articles de la fiche ajoutes
 * trois fois). Ce qui a ete retire ne va qu'au journal technique de l'etape, jamais sur le brouillon.
 */
final class VerificationStep extends EditorialStep
{
    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'verification';
    }

    public function timeoutSeconds(): int
    {
        return 400;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $written = EditorialArticle::fromArray($context->output('writing')['article'] ?? []);
        $research = $context->output('research');

        if ($written->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the written article');
        }

        $agent = new FactCheckAgent($this->prompts->compose('festilaw-context', 'eu-product-rules', 'editorial-guidelines', 'step-verification'), $context->length?->acceptedMax);
        $prompt = implode("\n\n", [
            $this->block('Research file', ['sources' => $research['sources'] ?? [], 'facts' => $research['facts'] ?? []]),
            $this->block('Article to check', $written->forPrompt()),
        ]);
        $reply = $this->ask($context, $agent, $prompt);
        $checked = EditorialArticle::fromArray($reply->structured());
        $problems = $this->problems($written, $checked);
        $usage = $reply->usage;

        if ($problems !== []) {
            $reply = $this->ask($context, $agent, spent: $usage, prompt: $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Your answer added material the article to check does not contain. Remove it and answer again in full', '- '.implode("\n- ", $problems)));
            $usage = $usage->plus($reply->usage);
            $checked = EditorialArticle::fromArray($reply->structured());
            $problems = $this->problems($written, $checked);
        }

        if ($problems !== []) {
            $this->unusable('the checked article adds material or loses an image: '.implode('; ', $problems), $usage);
        }

        $removed = array_values(array_filter(array_map(
            fn ($claim): array => is_array($claim) ? ['claim' => $this->text($claim, 'claim'), 'reason' => $this->text($claim, 'reason')] : [],
            $this->items($reply->structured(), 'removed_claims'),
        ), static fn (array $claim): bool => ($claim['claim'] ?? '') !== ''));

        return GenerationStepResult::continue(
            ['article' => $checked->toArray(), 'removed_claims' => $removed],
            $usage,
            $this->report($reply, $removed === []
                ? __('Chaque affirmation de l\'article est appuyée par le dossier de recherche.')
                : trans_choice('{1} Une affirmation non appuyée par le dossier a été retirée.|[2,*] :count affirmations non appuyées par le dossier ont été retirées.', count($removed), ['count' => count($removed)])),
        );
    }

    /**
     * Ce que la version verifiee ajoute au texte de depart (chiffre, lien, jeton) ou en perd (emplacement
     * d'image), dit au modele pour qu'il corrige. Vide : la version est acceptable.
     *
     * @return list<string>
     */
    private function problems(EditorialArticle $written, EditorialArticle $checked): array
    {
        if ($checked->isEmpty()) {
            return ['the article came back empty'];
        }

        $problems = array_values(array_filter(
            $this->guard->differences($written->text(), $checked->text()),
            static fn (string $difference): bool => ! str_ends_with($difference, 'added []'),
        ));
        $lostImages = array_diff(EditorialMarkdown::imagePlaceholders($written->body), EditorialMarkdown::imagePlaceholders($checked->body));

        if ($lostImages !== []) {
            $problems[] = 'image placeholders lost: '.implode(', ', $lostImages);
        }

        return $problems;
    }
}
