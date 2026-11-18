<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Bench;

use App\Data\Blog\Generation\Bench\BlogBenchConfiguration;
use App\Data\Blog\Generation\Bench\BlogBenchPlan;
use App\Data\Blog\Generation\Bench\BlogBenchStepRun;
use App\Data\Blog\Generation\Bench\BlogBenchTopic;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationSafetyStop;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\Bench\BlogBenchForecaster;
use App\Services\Blog\Generation\Bench\BlogBenchMeasures;
use App\Services\Blog\Generation\Bench\BlogBenchStepRunner;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\Editorial\Steps\EditorialStep;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use App\Services\Blog\Generation\Editorial\Steps\TranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\VerificationStep;
use App\Services\Blog\Generation\Editorial\Steps\WritingStep;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Ecrit un article du banc d'essai pour un sujet prepare et une configuration de modeles : redaction,
 * verification, relecture du style, versions francaise et espagnole, avec les etapes du pipeline telles
 * quelles. Le premier article d'un sujet en fait aussi generer l'image. Le resultat (textes de chaque etape,
 * releves, mesures) est ecrit une fois ; une reprise saute un article deja ecrit.
 *
 * L'arret de securite est le double du haut annonce pour la configuration, recherche et image comprises. Le cout
 * du sujet prepare en est deduit, comme dans une vraie generation ou la recherche fait partie de l'article.
 */
final readonly class RunBlogBenchArticleAction
{
    /** @var list<array{0: class-string<EditorialStep>, 1: array<string, string>}> */
    private const STEPS = [
        [WritingStep::class, []],
        [VerificationStep::class, []],
        [StyleStep::class, []],
        [TranslationStep::class, ['locale' => 'fr']],
        [TranslationStep::class, ['locale' => 'es']],
    ];

    public function __construct(
        private BlogBenchStepRunner $runner,
        private BlogBenchFileStorage $storage,
        private BlogBenchMeasures $measures,
        private GenerateBlogBenchImageAction $image,
        private BlogBenchForecaster $forecaster,
        private GenerationSafetyStop $safetyStop,
        private Container $container,
    ) {}

    /**
     * @param  array<string, mixed>  $prepared  le sujet prepare (PrepareBlogBenchTopicAction)
     * @return array<string, mixed> l'article : textes de chaque etape, releves, mesures, image
     *
     * @throws BlogBenchException
     */
    public function execute(string $run, BlogBenchTopic $topic, array $prepared, BlogBenchConfiguration $configuration, BlogBenchPlan $plan, BlogAiSettings $settings): array
    {
        $saved = $this->storage->article($run, $topic->key, $configuration->key);

        if ($saved !== null) {
            return $saved;
        }

        $settings = $settings->withModels([...$plan->sharedModels, ...$configuration->models, BlogAiModelCatalog::IMAGE_SETTING => $plan->imageModel]);
        $limit = $this->safetyStop->ceiling($this->forecaster->article($plan, $configuration)->highMicrousd);
        $scope = "{$topic->key} / {$configuration->key}";
        $preparationCost = (int) ($prepared['cost_microusd'] ?? 0);
        $outputs = (array) ($prepared['outputs'] ?? []);
        $steps = [];
        $cost = 0;

        foreach (self::STEPS as [$class, $parameters]) {
            /** @var EditorialStep $step */
            $step = $this->container->make($class, $parameters);
            $done = $this->runner->run($step, $topic, $settings, $outputs, $limit - $preparationCost - $cost, $plan->words);
            $cost += $done->usage->costMicrousd;
            $steps[$step->key()] = $done->record();
            $this->stopUnlessUsable($done, $scope, $step->key(), $steps);
            $outputs[$step->key()] = $done->result->output;
        }

        $image = $this->image->execute($run, $topic, $outputs['writing'], $plan->imageModel, $limit - $preparationCost - $cost);
        $written = array_intersect_key($outputs, array_flip(array_keys($steps)));
        $record = [
            'topic' => ['key' => $topic->key, 'kind' => $topic->kind->value, 'topic' => $topic->topic],
            'configuration' => ['key' => $configuration->key, 'name' => $configuration->name, 'models' => $configuration->models],
            'words_asked' => $plan->words,
            'limit_microusd' => $limit,
            'preparation_cost_microusd' => $preparationCost,
            'cost_microusd' => $cost,
            'duration_ms' => array_sum(array_column($steps, 'duration_ms')),
            'steps' => $steps,
            'image' => $image,
            'measures' => $this->measures->article($outputs, $plan->words),
            'outputs' => $written,
            'finished_at' => CarbonImmutable::now()->toIso8601String(),
        ];
        $this->storage->saveArticle($run, $topic->key, $configuration->key, $record);

        return $record;
    }

    /**
     * Le cout d'une etape qui n'aboutit pas va au journal : rien n'est ecrit pour cet article, que la reprise
     * refera.
     *
     * @param  array<string, array<string, mixed>>  $steps
     *
     * @throws BlogBenchException
     */
    private function stopUnlessUsable(BlogBenchStepRun $done, string $scope, string $key, array $steps): void
    {
        $exception = match (true) {
            $done->creditExhausted => BlogBenchException::creditExhausted($done->error),
            $done->overBudget => BlogBenchException::overBudget($scope, $key),
            ! $done->succeeded() => BlogBenchException::stepFailed($scope, $key, $done->error),
            $done->result->stopsGeneration() => BlogBenchException::stoppedWithoutArticle($scope, $key, $done->result->stopReason?->value),
            default => null,
        };

        if ($exception !== null) {
            Log::error('Blog bench article stopped.', ['scope' => $scope, 'step' => $key, 'steps' => $steps, 'exception' => $done->error ?? $exception]);

            throw $exception;
        }
    }
}
