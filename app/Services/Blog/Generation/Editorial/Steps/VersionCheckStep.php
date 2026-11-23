<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\VersionCheckAgent;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogFactGuard;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;
use App\Services\Blog\Generation\Editorial\EditorialTypography;
use InvalidArgumentException;

/**
 * Controle d'une version (francaise ou espagnole), apres son adaptation : le modele de la verification la
 * compare au texte anglais verifie et rend les passages qui disent autre chose sur un fait, avec leur correction.
 * Le banc d'essai du 18/11 comptait sept versions sur dix-sept qui changeaient un fait apres la verification
 * (une alternative devenue une obligation, une condition perdue), et personne ne relisait les versions.
 *
 * Le code applique lui-meme les corrections, pour que le style de la version ne bouge pas. Une correction est
 * ecartee quand son passage ne se trouve pas une seule fois dans la version, ou quand elle ajouterait un lien,
 * un jeton, un emplacement d'image ou un chiffre absent du texte anglais.
 *
 * Le controle recoit le lexique de sa langue, comme la traduction. Sans lui, il a « corrige » vers l'anglais un
 * terme officiel juste (« Attention » devenu « Attention » ou « Avertissements », essai du 19/11).
 */
final class VersionCheckStep extends EditorialStep
{
    private const LANGUAGES = ['fr' => 'French', 'es' => 'Spanish'];

    private const FIELDS = ['title', 'excerpt', 'metaTitle', 'metaDescription', 'body'];

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
        private readonly EditorialTypography $typography,
        private readonly string $locale,
    ) {
        if (! array_key_exists($locale, self::LANGUAGES)) {
            throw new InvalidArgumentException("No version check for locale [{$locale}].");
        }

        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'version_check_'.$this->locale;
    }

    public function timeoutSeconds(): int
    {
        return 300;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $english = EditorialArticle::fromArray($context->output('style')['article'] ?? []);
        $version = EditorialArticle::fromArray($context->output('translation_'.$this->locale)['article'] ?? []);

        if ($english->isEmpty() || $version->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the English article and its version');
        }

        $language = self::LANGUAGES[$this->locale];
        $agent = new VersionCheckAgent(implode("\n\n", [
            $this->prompts->get('festilaw-context'),
            $this->prompts->get('lexicon-'.$this->locale),
            $this->prompts->render('step-version-check', ['language' => $language]),
        ]));
        $reply = $this->ask($context, $agent, implode("\n\n", [
            $this->block('English article (checked against the sources)', $english->forPrompt()),
            $this->block("{$language} version to check", $version->forPrompt()),
        ]));

        [$checked, $applied, $setAside] = $this->apply($version, $english, $this->items($reply->structured(), 'corrections'));
        $checked = $this->typography->article($checked, $this->locale);

        return GenerationStepResult::continue(
            ['locale' => $this->locale, 'article' => $checked->toArray(), 'corrections' => $applied, 'set_aside' => $setAside],
            $reply->usage,
            $this->summary($reply, count($applied), count($setAside)),
        );
    }

    /**
     * Le compte rendu commence par ce que le code a fait, qui ne peut pas se tromper, puis la description de
     * l'IA (decision du 23/11) : ecrite au present, elle laissait croire l'ecart encore dans le brouillon alors
     * qu'il etait corrige. Une correction ecartee est dite a verifier.
     */
    private function summary(BlogAgentReply $reply, int $applied, int $setAside): string
    {
        if ($applied === 0 && $setAside === 0) {
            return __('La version dit la même chose que le texte anglais.');
        }

        $sentences = [];

        if ($applied > 0) {
            $sentences[] = trans_choice('{1} Un passage de la version a été corrigé pour dire ce que dit le texte anglais.|[2,*] :count passages de la version ont été corrigés pour dire ce que dit le texte anglais.', $applied, ['count' => $applied]);
        }

        $described = $this->text($reply->structured(), 'report_fr');

        if ($described !== '') {
            $sentences[] = $described;
        }

        if ($setAside > 0) {
            $sentences[] = trans_choice('{1} Un écart relevé n\'a pas pu être corrigé automatiquement. Il reste à vérifier dans la version.|[2,*] :count écarts relevés n\'ont pas pu être corrigés automatiquement. Ils restent à vérifier dans la version.', $setAside, ['count' => $setAside]);
        }

        return implode(' ', $sentences);
    }

    /**
     * Applique chaque correction qui retrouve son passage une seule fois dans la version et n'ajoute rien que le
     * texte anglais n'ait pas.
     *
     * @param  list<mixed>  $corrections
     * @return array{0: EditorialArticle, 1: list<array{original: string, corrected: string, reason: string}>, 2: list<array{original: string, why: string}>}
     */
    private function apply(EditorialArticle $version, EditorialArticle $english, array $corrections): array
    {
        $fields = array_combine(self::FIELDS, array_map(static fn (string $field): string => $version->{$field}, self::FIELDS));
        $englishNumbers = $this->guard->numbers($english->text());
        $applied = [];
        $setAside = [];

        foreach ($corrections as $correction) {
            $original = is_array($correction) ? $this->text($correction, 'original') : '';
            $corrected = is_array($correction) ? $this->text($correction, 'corrected') : '';

            if ($original === '' || $corrected === '' || $original === $corrected) {
                continue;
            }

            $why = $this->unsafe($original, $corrected, $englishNumbers)
                ?? (array_sum(array_map(static fn (string $text): int => substr_count($text, $original), $fields)) === 1 ? null : 'passage not found once in the version');

            if ($why !== null) {
                $setAside[] = ['original' => $original, 'why' => $why];

                continue;
            }

            foreach ($fields as $field => $text) {
                $fields[$field] = str_replace($original, $corrected, $text);
            }

            $applied[] = ['original' => $original, 'corrected' => $corrected, 'reason' => $this->text($correction, 'reason')];
        }

        return [new EditorialArticle(...$fields), $applied, $setAside];
    }

    /**
     * Ce qu'une correction ajouterait et que la version ne doit pas recevoir : un lien, un jeton, un emplacement
     * d'image, ou un chiffre absent du texte anglais. Nul quand elle est sure.
     *
     * @param  list<string>  $englishNumbers
     */
    private function unsafe(string $original, string $corrected, array $englishNumbers): ?string
    {
        if (EditorialMarkdown::linkTokens($original) !== EditorialMarkdown::linkTokens($corrected)
            || EditorialMarkdown::imagePlaceholders($original) !== EditorialMarkdown::imagePlaceholders($corrected)
            || $this->linksOf($original) !== $this->linksOf($corrected)) {
            return 'links, tokens or images changed';
        }

        $added = array_diff($this->guard->numbers($corrected), $this->guard->numbers($original), $englishNumbers);

        return $added === [] ? null : 'number absent from the English article: '.implode(', ', $added);
    }

    /** @return list<string> */
    private function linksOf(string $text): array
    {
        preg_match_all('/\]\(([^)\s]+)\)/', $text, $matches);

        return $matches[1];
    }
}
