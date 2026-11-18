<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationCostForecast;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationLength;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;

/**
 * La fourchette de cout d'un article, avant de le lancer : la somme, etape par etape, de ce que coutent les
 * appels prevus par le profil `blog.ai.forecast.steps`, aux prix des modeles choisis. Calcul pur, aucune
 * requete.
 *
 * La borne basse compte un appel par etape et une reflexion faible ; la borne haute, les corrections et
 * seconds passages et la reflexion a son plus haut. Les jetons comptent au prix que le modele coute vraiment
 * quand on l'a mesure : appliquer a tous la marge mesuree sur DeepSeek (2,8 fois le catalogue) bloquait des
 * reglages a Haiku, facture au prix du catalogue.
 */
final readonly class BlogGenerationCostForecaster
{
    /** @param  array<string, array<string, mixed>>  $profile  par etape : reglage de modele, jetons, appels, recherches */
    public function __construct(
        private BlogAiCostEstimator $prices,
        private BlogAiModelCatalog $models,
        private array $profile,
        private int $defaultMarginPercent,
        private BlogWritingSignalsInspector $words,
    ) {}

    public function forSettings(BlogAiSettings $settings, ?int $words = null, ?GenerationImageOptions $images = null, bool $topicImposed = false): GenerationCostForecast
    {
        return $this->forecast($settings->models, $words ?? $settings->articleWords, $images ?? GenerationImageOptions::fromSettings($settings), $topicImposed);
    }

    /**
     * Le cout annonce d'une generation, d'apres ce qu'elle porte : la longueur et les images d'un article, ou le
     * texte d'une traduction assistee, comptee comme l'etape de version francaise. Fige au lancement, il fixe
     * son arret de securite.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function forGeneration(BlogAiSettings $settings, BlogGenerationType $type, ?array $payload, bool $topicImposed): GenerationCostForecast
    {
        if ($type === BlogGenerationType::Translation) {
            $article = (array) ($payload['translation']['article'] ?? []);
            $words = $this->words->words(trim((string) ($article['title'] ?? '')."\n\n".(string) ($article['body'] ?? '')));

            return $this->forecast($settings->models, $words, new GenerationImageOptions(BlogAiImageMode::None, 1), true, ['translation_fr']);
        }

        return $this->forSettings($settings, GenerationLength::wordsFromPayload($payload), GenerationImageOptions::fromPayload($payload), $topicImposed);
    }

    /**
     * Un sujet impose se passe de veille. Une image generee compte en bas la seule image principale, a son prix
     * mesure le plus bas, et en haut toutes les images, a leur prix mesure le plus haut. Les jetons du profil
     * sont ceux de DeepSeek et de GPT-6 Luna ; un modele qui en compte plus pour le meme texte (Anthropic) les
     * multiplie par son facteur.
     *
     * @param  array<string, string>  $models  modele par reglage (sujet, recherche, redaction...)
     * @param  list<string>|null  $onlySteps  les seules etapes a compter (banc d'essai : la recherche partagee a part) ; nul : toutes
     */
    public function forecast(array $models, int $words, GenerationImageOptions $images, bool $topicImposed = false, ?array $onlySteps = null): GenerationCostForecast
    {
        $low = 0;
        $high = 0;

        foreach ($this->profile as $step => $profile) {
            if (($step === 'topic_scan' && $topicImposed) || ($onlySteps !== null && ! in_array($step, $onlySteps, true))) {
                continue;
            }

            $model = $models[(string) $profile['setting']] ?? null;
            $factor = $this->models->tokenFactorPercent($model);
            $input = intdiv(((int) ($profile['input'] ?? 0) + intdiv((int) ($profile['input_per_1000_words'] ?? 0) * $words, 1_000)) * $factor, 100);
            $visible = intdiv(((int) ($profile['output'] ?? 0) + intdiv((int) ($profile['output_per_1000_words'] ?? 0) * $words, 1_000)) * $factor, 100);
            [$lowReasoning, $highReasoning] = $this->reasoning($model, $profile['reasoning'] ?? [0, 0]);
            [$lowCalls, $highCalls] = $profile['calls'] ?? [1, 1];
            $measured = $this->models->priceMarginPercent($model);
            $searches = $this->prices->webSearchesMicrousd((int) ($profile['searches'] ?? 0));

            $low += $searches + $this->calls($model, $input, $visible, $lowReasoning, (int) $lowCalls, $measured ?? 100);
            $high += $searches + $this->calls($model, $input, $visible, $highReasoning, (int) $highCalls, $measured ?? $this->defaultMarginPercent);
        }

        if ($images->mode === BlogAiImageMode::Generated) {
            [$imageLow, $imageHigh] = $this->models->imagePriceMicrousd((string) ($models[BlogAiModelCatalog::IMAGE_SETTING] ?? ''));
            $low += $imageLow;
            $high += $imageHigh * $images->maxImages;
        }

        return new GenerationCostForecast($low, $high);
    }

    /**
     * La reflexion de l'etape, sauf pour un modele qui ignore l'effort demande : la sienne. Une etape sans
     * reflexion (effort « none ») n'en a pour aucun modele.
     *
     * @param  array{0: int, 1: int}  $step
     * @return array{0: int, 1: int}
     */
    private function reasoning(?string $model, array $step): array
    {
        return $step[1] > 0 ? ($this->models->reasoningTokens($model) ?? $step) : $step;
    }

    /** Un appel apres le premier (correction, second passage) relit en plus la reponse precedente. */
    private function calls(?string $model, int $input, int $visible, int $reasoning, int $calls, int $marginPercent): int
    {
        $read = $calls * $input + ($calls - 1) * $visible;
        $written = $calls * ($visible + $reasoning);

        return intdiv($this->prices->catalogMicrousd($model, $read, $written) * $marginPercent, 100);
    }
}
