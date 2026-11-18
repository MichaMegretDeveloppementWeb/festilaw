<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Bench;

use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\Bench\BlogBenchStepRun;
use App\Data\Blog\Generation\Bench\BlogBenchTopic;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogAiProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Joue une etape du pipeline pour le banc d'essai, avec les memes essais et la meme attente que le moteur
 * (BlogGenerationStepRunner), mais sans rien ecrire en base : le banc ne cree aucune generation (identifiant
 * 0 au journal). L'etape recoit ce qu'en recevrait une generation au sujet impose, avec une image generee.
 * Le cout des essais rates est garde, car il a ete facture, et deduit de ce qui reste pour l'essai suivant.
 */
final readonly class BlogBenchStepRunner
{
    public function __construct(
        private ArticleLengthRules $length,
        private int $maxAttempts,
        private int $retryWaitSeconds,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $outputs  resultat des etapes deja jouees, par cle d'etape
     * @param  int  $budgetLeftMicrousd  ce qui reste sous le plafond par article avant cette etape
     */
    public function run(GenerationStep $step, BlogBenchTopic $topic, BlogAiSettings $settings, array $outputs, int $budgetLeftMicrousd, int $words): BlogBenchStepRun
    {
        $spent = GenerationUsage::none();
        $startedAt = hrtime(true);

        for ($attempt = 1; ; $attempt++) {
            try {
                $result = $step->execute($this->context($topic, $settings, $outputs, $attempt, $budgetLeftMicrousd - $spent->costMicrousd, $words));

                return new BlogBenchStepRun($result, $spent->plus($result->usage), $attempt, $this->elapsedMs($startedAt));
            } catch (BlogAiProviderException $e) {
                $spent = $e->usage === null ? $spent : $spent->plus($e->usage);

                if ($e->overBudget || $e->creditExhausted || $attempt >= $this->maxAttempts) {
                    return new BlogBenchStepRun(null, $spent, $attempt, $this->elapsedMs($startedAt), $e, $e->overBudget, $e->creditExhausted);
                }
            } catch (Throwable $e) {
                if ($attempt >= $this->maxAttempts) {
                    return new BlogBenchStepRun(null, $spent, $attempt, $this->elapsedMs($startedAt), $e);
                }
            }

            Log::warning('Blog bench step attempt failed, it will be retried.', ['step' => $step->key(), 'topic' => $topic->key, 'attempt' => $attempt, 'exception' => $e]);
            Sleep::for($this->retryWaitSeconds)->seconds();
        }
    }

    /** @param  array<string, array<string, mixed>>  $outputs */
    private function context(BlogBenchTopic $topic, BlogAiSettings $settings, array $outputs, int $attempt, int $budgetLeftMicrousd, int $words): GenerationStepContext
    {
        return new GenerationStepContext(
            generationId: 0,
            type: BlogGenerationType::Article,
            topicHint: $topic->topic,
            instructions: null,
            settings: $settings,
            outputs: $outputs,
            payload: [GenerationImageOptions::PAYLOAD_KEY => (new GenerationImageOptions(BlogAiImageMode::Generated, 1))->toPayload()],
            attempt: $attempt,
            budgetLeftMicrousd: $budgetLeftMicrousd,
            length: $this->length->for($words),
        );
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
