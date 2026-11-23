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
use App\Services\Blog\Generation\Editorial\EditorialTypography;

/**
 * Etapes « version francaise » et « version espagnole », chacune suivie de son controle (VersionCheckStep). Depuis le 18/11, l'article anglais verifie sert de
 * matiere a un secretaire de redaction de la langue, qui ecrit lui-meme titre, chapo et intertitres d'apres
 * la charte de sa presse (writing-voice-xx.md, consigne step-adaptation-xx.md, ecrite dans la langue) : une
 * simple traduction gardait le squelette anglais (brouillon 99, relu par Micha). Les memes jetons de liens et
 * emplacements d'images que l'anglais sont exiges : sinon une demande de correction, puis l'echec de
 * l'etape, que le moteur reessaie. Un nombre ecrit autrement ne bloque rien mais reste au journal.
 *
 * Le controle des signes de la langue releve les phrases, titres et intertitres qui en portent encore
 * (formules de titre et calques compris). Au-dela du seuil (config blog.ai.style.max_flagged_sentences), ils
 * sont renvoyes au modele pour un second passage, un seul, garde seulement s'il respecte les memes liens,
 * jetons et images. La typographie de la langue est posee ensuite en code (EditorialTypography).
 */
final class TranslationStep extends EditorialStep
{
    private const LOCALES = ['fr', 'es'];

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
        private readonly BlogWritingSignalsInspector $signals,
        private readonly EditorialTypography $typography,
        private readonly string $locale,
        private readonly int $maxFlaggedSentences = 0,
    ) {
        if (! in_array($locale, self::LOCALES, true)) {
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

    /** Le francais et l'espagnol comptent environ un quart de mots de plus que l'anglais (655 et 608 pour 522, le 17/11). */
    private function translatedWords(GenerationStepContext $context): ?int
    {
        return $context->length === null ? null : (int) ceil($context->length->acceptedMax * 1.25);
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
        $agent = new TranslatorAgent(implode("\n\n", [
            $this->prompts->get('festilaw-context'),
            $this->prompts->get('editorial-guidelines'),
            $this->prompts->get('writing-voice-'.$this->locale),
            $this->prompts->get('lexicon-'.$this->locale),
            $this->prompts->get('step-adaptation-'.$this->locale),
        ]), $this->translatedWords($context));
        $newCategory = is_string($writing['new_category'] ?? null) && $writing['new_category'] !== '' ? $writing['new_category'] : null;
        $prompt = implode("\n\n", array_filter([
            $this->block('Article', $source->forPrompt()),
            $this->block('Image descriptions', array_values(array_filter($images, static fn (array $image): bool => $image['description'] !== ''))),
            // Une categorie que la redaction a proposee pour l'article : son nom dans cette langue (decision du 23/11).
            $newCategory === null ? null : $this->block('New category', $newCategory),
        ]));

        $reply = $this->ask($context, $agent, $prompt);
        $usage = $reply->usage;
        $problems = $this->problems($source, $reply);
        $lengths = $this->lengthProblems($reply);

        // Une longueur depassee demande une correction, sans jamais faire echouer l'etape : la reponse corrigee
        // n'est gardee que si elle reste juste, ou si la premiere ne l'etait pas.
        if ($problems !== [] || $lengths !== []) {
            $correction = $this->ask($context, $agent, spent: $usage, prompt: $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Fix these problems and answer again in full', '- '.implode("\n- ", [...$problems, ...$lengths])));
            $usage = $usage->plus($correction->usage);
            $correctionProblems = $this->problems($source, $correction);

            if ($correctionProblems === [] || $problems !== []) {
                [$reply, $problems] = [$correction, $correctionProblems];
            }
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

        $translated = $this->typography->article($translated, $this->locale);
        $alts = [];

        foreach ($this->items($reply->structured(), 'image_alts') as $alt) {
            if (is_array($alt) && is_int($alt['number'] ?? null) && $this->text($alt, 'alt') !== '') {
                $alts[(string) $alt['number']] = $this->typography->apply($this->text($alt, 'alt'), $this->locale);
            }
        }

        $categoryName = $newCategory === null ? '' : $this->text($reply->structured(), 'category_name');

        return GenerationStepResult::continue(
            [
                'locale' => $this->locale,
                'article' => $translated->toArray(),
                'image_alts' => $alts,
                'category_name' => $categoryName === '' ? null : $this->typography->apply(mb_substr($categoryName, 0, 60), $this->locale),
                'number_warnings' => array_values(array_filter(
                    $this->guard->differences($source->text(), $translated->text()),
                    static fn (string $difference): bool => str_starts_with($difference, 'numbers'),
                )),
                'second_pass' => $secondPass,
                'signals' => $signals,
            ],
            $usage,
            $this->report($reply, $this->locale === 'fr'
                ? __('La version française a été écrite d\'après la charte de la presse française, avec le lexique du domaine.')
                : __('La version espagnole a été écrite d\'après la charte de la presse espagnole, avec le lexique du domaine.')),
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

    /**
     * Les longueurs depassees de la version : l'extrait et la description pour Google (decision du 23/11).
     *
     * @return list<string>
     */
    private function lengthProblems(BlogAgentReply $reply): array
    {
        $translated = EditorialArticle::fromArray($reply->structured());
        $problems = [];
        $excerpt = mb_strlen($translated->excerpt);
        $description = mb_strlen($translated->metaDescription);

        if ($excerpt > self::EXCERPT_MAX_CHARACTERS) {
            $problems[] = "excerpt has {$excerpt} characters. Write it in one or two sentences of about 160 characters.";
        }

        if ($description > self::META_DESCRIPTION_MAX_CHARACTERS) {
            $problems[] = "meta_description has {$description} characters. Keep it just under 160.";
        }

        return $problems;
    }
}
