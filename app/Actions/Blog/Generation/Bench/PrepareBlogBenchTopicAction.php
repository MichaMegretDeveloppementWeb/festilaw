<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Bench;

use App\Data\Blog\Generation\ArticleSpendLimit;
use App\Data\Blog\Generation\Bench\BlogBenchPlan;
use App\Data\Blog\Generation\Bench\BlogBenchStepRun;
use App\Data\Blog\Generation\Bench\BlogBenchTopic;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\Bench\BlogBenchStepRunner;
use App\Services\Blog\Generation\Editorial\Steps\EditorialStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchNotesStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicStep;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Prepare un sujet du banc d'essai : le cadrage du sujet impose, la recherche sur le web et le dossier de faits,
 * avec les modeles partages du banc. Le resultat est ecrit une fois, puis relu par chaque configuration : seule
 * l'ecriture change d'une configuration a l'autre, et une reprise ne repaie pas une recherche deja faite.
 *
 * La nature du sujet (fond ou actualite) est celle du banc, pas celle que choisit le modele : le cahier des
 * charges demande trois sujets de fond et deux d'actualite. Un sujet que le blog a deja traite est garde, car
 * le banc ne publie rien.
 */
final readonly class PrepareBlogBenchTopicAction
{
    private const STEPS = [TopicStep::class, ResearchNotesStep::class, ResearchStep::class];

    public function __construct(
        private BlogBenchStepRunner $runner,
        private BlogBenchFileStorage $storage,
        private ArticleSpendLimit $spendLimit,
        private Container $container,
    ) {}

    /**
     * @return array<string, mixed> le sujet prepare : sorties des trois etapes, releve de chacune, cout
     *
     * @throws BlogBenchException
     */
    public function execute(string $run, BlogBenchTopic $topic, BlogBenchPlan $plan, BlogAiSettings $settings): array
    {
        $saved = $this->storage->topic($run, $topic->key);

        if ($saved !== null) {
            return $saved;
        }

        $settings = $settings->withModels($plan->sharedModels);
        $limit = $this->spendLimit->limit($settings->articleSpendCapMicrousd());
        $outputs = [];
        $steps = [];
        $cost = 0;

        foreach (self::STEPS as $class) {
            /** @var EditorialStep $step */
            $step = $this->container->make($class);
            $done = $this->runner->run($step, $topic, $settings, $outputs, $limit - $cost, $plan->words);
            $cost += $done->usage->costMicrousd;
            $steps[$step->key()] = $done->record();
            $this->stopUnlessUsable($done, $topic, $step->key(), $steps);

            $outputs[$step->key()] = $step instanceof TopicStep ? [...$done->result->output, 'kind' => $topic->kind->value] : $done->result->output;
        }

        $record = [
            'topic' => ['key' => $topic->key, 'kind' => $topic->kind->value, 'topic' => $topic->topic],
            'models' => $plan->sharedModels,
            'cost_microusd' => $cost,
            'duration_ms' => array_sum(array_column($steps, 'duration_ms')),
            'steps' => $steps,
            'outputs' => $outputs,
            'finished_at' => CarbonImmutable::now()->toIso8601String(),
        ];
        $this->storage->saveTopic($run, $topic->key, $record);

        return $record;
    }

    /**
     * Le cout d'une etape qui n'aboutit pas va au journal : rien n'est ecrit pour ce sujet, que la reprise refera.
     *
     * @param  array<string, array<string, mixed>>  $steps
     *
     * @throws BlogBenchException
     */
    private function stopUnlessUsable(BlogBenchStepRun $done, BlogBenchTopic $topic, string $key, array $steps): void
    {
        $exception = match (true) {
            $done->creditExhausted => BlogBenchException::creditExhausted($done->error),
            $done->overBudget => BlogBenchException::overBudget($topic->key, $key),
            ! $done->succeeded() => BlogBenchException::stepFailed($topic->key, $key, $done->error),
            $done->result->stopsGeneration() && $done->result->stopReason !== BlogGenerationOutcomeReason::DuplicateTopic => BlogBenchException::stoppedWithoutArticle($topic->key, $key, $done->result->stopReason?->value),
            default => null,
        };

        if ($exception !== null) {
            Log::error('Blog bench topic stopped.', ['topic' => $topic->key, 'step' => $key, 'steps' => $steps, 'exception' => $done->error ?? $exception]);

            throw $exception;
        }
    }
}
