<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\TranslatorAgent;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogFactGuard;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;

/**
 * L'unique etape d'une traduction assistee (tache 6.14) : la version d'un article ecrit a la main dans une
 * autre langue du blog, avec le contexte de l'entreprise, les regles d'ecriture et le lexique de la langue
 * visee. Memes liens et memes emplacements d'images que le texte de depart, sinon une demande de correction,
 * puis l'echec de l'etape. Le resultat reste au journal de l'etape ; le formulaire vient l'y chercher.
 */
final class AssistedTranslationStep extends EditorialStep
{
    private const LANGUAGES = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish'];

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'translation';
    }

    public function timeoutSeconds(): int
    {
        return 500;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $request = $context->payload['translation'] ?? null;
        $from = is_array($request) ? (string) ($request['from'] ?? '') : '';
        $to = is_array($request) ? (string) ($request['to'] ?? '') : '';
        $source = EditorialArticle::fromArray(is_array($request['article'] ?? null) ? $request['article'] : []);

        if (! isset(self::LANGUAGES[$from], self::LANGUAGES[$to]) || ($source->title === '' && $source->body === '')) {
            throw BlogEditorialException::missingInput($this->key(), 'the text to translate');
        }

        $agent = new TranslatorAgent(implode("\n\n", array_filter([
            $this->prompts->get('festilaw-context'),
            $this->prompts->get('editorial-guidelines'),
            $this->prompts->get('writing-voice-'.$to),
            $to === 'en' ? null : $this->prompts->get('lexicon-'.$to),
            $this->prompts->render('step-translation', ['language' => self::LANGUAGES[$to]]),
        ])));
        $prompt = implode("\n\n", [
            $this->block('Language of the original', self::LANGUAGES[$from]),
            $this->block('Article', $source->forPrompt()),
        ]);

        $reply = $this->ask($context, $agent, $prompt);
        $usage = $reply->usage;
        $problems = $this->problems($source, $reply);

        if ($problems !== []) {
            $reply = $this->ask($context, $agent, $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Fix these problems and answer again in full', '- '.implode("\n- ", $problems)));
            $usage = $usage->plus($reply->usage);
            $problems = $this->problems($source, $reply);
        }

        if ($problems !== []) {
            throw BlogEditorialException::unusableAnswer($this->key(), implode(' ', $problems));
        }

        return GenerationStepResult::continue(
            ['locale' => $to, 'article' => EditorialArticle::fromArray($reply->structured())->toArray()],
            $usage,
            $this->report($reply, __('La traduction a été rédigée dans la langue, avec les règles d\'écriture du blog.')),
        );
    }

    /** @return list<string> */
    private function problems(EditorialArticle $source, BlogAgentReply $reply): array
    {
        $translated = EditorialArticle::fromArray($reply->structured());

        if (($source->title !== '' && $translated->title === '') || ($source->body !== '' && $translated->body === '')) {
            return ['The translation lacks the title or the body of the original.'];
        }

        return array_values(array_map(
            static fn (string $difference): string => "Keep exactly the same {$difference}.",
            array_filter(
                $this->guard->differences($source->text(), $translated->text()),
                static fn (string $difference): bool => ! str_starts_with($difference, 'numbers'),
            ),
        ));
    }
}
