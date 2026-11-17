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
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;

/**
 * Etapes 8 et 9 · la version francaise ou espagnole, redigee dans la langue avec ses regles d'ecriture et le
 * lexique du domaine (annexe A.8, A.12 point 4). Les memes jetons de liens et emplacements d'images que
 * l'anglais sont exiges : sinon une demande de correction, puis l'echec de l'etape, que le moteur reessaie.
 * Un nombre ecrit autrement ne bloque rien mais reste au journal.
 *
 * Comme la relecture du style en anglais, le controle des signes de la langue releve les phrases qui en
 * portent encore. Au-dela du seuil (config blog.ai.style.max_flagged_sentences), elles sont renvoyees au
 * modele pour un second passage, un seul, garde seulement s'il respecte les memes liens, jetons et images.
 */
final class TranslationStep extends EditorialStep
{
    private const LANGUAGES = ['fr' => 'French', 'es' => 'Spanish'];

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
        private readonly BlogWritingSignalsInspector $signals,
        private readonly string $locale,
        private readonly int $maxFlaggedSentences = 0,
    ) {
        if (! isset(self::LANGUAGES[$locale])) {
            throw new \InvalidArgumentException("No translation step for locale [{$locale}].");
        }

        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'translation_'.$this->locale;
    }

    public function timeoutSeconds(): int
    {
        return 500;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $source = EditorialArticle::fromArray($context->output('style')['article'] ?? []);
        $writing = $context->output('writing');

        if ($source->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the English article');
        }

        $images = [['number' => 0, 'description' => $writing['featured_image']['description'] ?? ''], ...array_map(
            static fn (array $image): array => ['number' => $image['number'], 'description' => $image['description'] ?? ''],
            $writing['inline_images'] ?? [],
        )];
        $language = self::LANGUAGES[$this->locale];
        $agent = new TranslatorAgent(implode("\n\n", [
            $this->prompts->get('festilaw-context'),
            $this->prompts->get('editorial-guidelines'),
            $this->prompts->get('writing-voice-'.$this->locale),
            $this->prompts->get('lexicon-'.$this->locale),
            $this->prompts->render('step-translation', ['language' => $language]),
        ]));
        $prompt = implode("\n\n", [
            $this->block('Article', $source->forPrompt()),
            $this->block('Image descriptions', array_values(array_filter($images, static fn (array $image): bool => $image['description'] !== ''))),
        ]);

        $reply = $this->ask($context, $agent, $prompt);
        $usage = $reply->usage;
        $problems = $this->problems($source, $reply);

        if ($problems !== []) {
            $correction = $this->ask($context, $agent, spent: $usage, prompt: $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Fix these problems and answer again in full', '- '.implode("\n- ", $problems)));
            $usage = $usage->plus($correction->usage);
            $reply = $correction;
            $problems = $this->problems($source, $reply);
        }

        if ($problems !== []) {
            $this->unusable(implode(' ', $problems), $usage);
        }

        $translated = EditorialArticle::fromArray($reply->structured());
        $signs = $this->prompts->lines('signals-'.$this->locale);
        $signals = $this->signals->inspect($this->checkedText($translated), $signs);
        $secondPass = null;

        if (count($signals['flagged']) > $this->maxFlaggedSentences) {
            $second = $this->ask($context, $agent, spent: $usage, prompt: $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Sentences that still show signs of generated text', implode("\n", array_map(
                    static fn (array $flag): string => '- '.$flag['sentence'].' ('.implode(', ', $flag['signs']).')',
                    $signals['flagged'],
                ))));
            $usage = $usage->plus($second->usage);
            $secondProblems = $this->problems($source, $second);
            $secondPass = ['flagged_before' => count($signals['flagged']), 'applied' => $secondProblems === [], 'differences' => $secondProblems];

            if ($secondProblems === []) {
                $reply = $second;
                $translated = EditorialArticle::fromArray($reply->structured());
                $signals = $this->signals->inspect($this->checkedText($translated), $signs);
            }
        }

        $alts = [];

        foreach ($this->items($reply->structured(), 'image_alts') as $alt) {
            if (is_array($alt) && is_int($alt['number'] ?? null) && $this->text($alt, 'alt') !== '') {
                $alts[(string) $alt['number']] = $this->text($alt, 'alt');
            }
        }

        return GenerationStepResult::continue(
            [
                'locale' => $this->locale,
                'article' => $translated->toArray(),
                'image_alts' => $alts,
                'number_warnings' => array_values(array_filter(
                    $this->guard->differences($source->text(), $translated->text()),
                    static fn (string $difference): bool => str_starts_with($difference, 'numbers'),
                )),
                'second_pass' => $secondPass,
                'signals' => $signals,
            ],
            $usage,
            $this->report($reply, $this->locale === 'fr'
                ? __('La version française a été rédigée dans la langue, avec le lexique du domaine.')
                : __('La version espagnole a été rédigée dans la langue, avec le lexique du domaine.')),
        );
    }

    /** Le texte que le controle des signes lit : le titre, comme un intertitre, puis le corps. */
    private function checkedText(EditorialArticle $article): string
    {
        return '# '.$article->title."\n\n".$article->body;
    }

    /**
     * Ce qui empeche de garder la traduction : texte vide, jetons de liens ou emplacements d'images differents.
     *
     * @return list<string>
     */
    private function problems(EditorialArticle $source, BlogAgentReply $reply): array
    {
        $translated = EditorialArticle::fromArray($reply->structured());

        if ($translated->isEmpty()) {
            return ['The translation has no title or no body.'];
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
