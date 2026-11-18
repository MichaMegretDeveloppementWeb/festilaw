<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\Bench\PrepareBlogBenchTopicAction;
use App\Actions\Blog\Generation\Bench\RunBlogBenchArticleAction;
use App\Data\Blog\Generation\Bench\BlogBenchPlan;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationCostForecast;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\Bench\BlogBenchForecaster;
use App\Services\Blog\Generation\Bench\BlogBenchMeasures;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Banc d'essai des modeles (SC7) : rejoue le pipeline editorial sur les sujets et les configurations de
 * `blog.ai.bench`, sans rien creer dans le site, et ecrit textes et mesures dans des fichiers. Chaque article
 * appelle vraiment les modeles : `--dry` donne d'abord le cout estime, sans aucun appel.
 *
 * Un sujet ou un article qui echoue est journalise et le banc passe au suivant ; seul un credit epuise
 * l'arrete. `--run` reprend un banc la ou il s'est arrete, sans repayer ce qui est deja ecrit. C'est un outil
 * lance a la main en local : il dure plus d'une heure, ce que l'hebergement ne garantit pas.
 */
final class RunBlogBench extends Command
{
    protected $signature = 'festilaw:blog-bench
        {--dry : Donne le cout estime du banc, sans aucun appel}
        {--run= : Nom du banc a reprendre (sinon un nouveau banc, a la date et a l\'heure du lancement)}
        {--topic=* : Sujets a jouer (cles de blog.ai.bench.topics), tous par defaut}
        {--configuration=* : Configurations a jouer (cles de blog.ai.bench.configurations), toutes par defaut}';

    protected $description = 'Banc d\'essai des modeles : rejoue le pipeline sur des sujets fixes, sans rien creer dans le site.';

    public function handle(
        BlogBenchPlan $plan,
        BlogAiSettingsService $settingsService,
        BlogBenchForecaster $forecaster,
        PrepareBlogBenchTopicAction $prepare,
        RunBlogBenchArticleAction $write,
        BlogBenchMeasures $measures,
        BlogBenchFileStorage $storage,
        BlogAiCostFormatter $dollars,
    ): int {
        [$plan, $unknown] = $plan->only((array) $this->option('topic'), (array) $this->option('configuration'));

        if ($unknown !== []) {
            $this->error(BlogBenchException::unknownKeys($unknown)->getUserMessage());

            return self::FAILURE;
        }

        $settings = $settingsService->current();

        if ($this->option('dry')) {
            $this->showForecast($forecaster->forecast($plan, $settings), count($plan->topics), $dollars);

            return self::SUCCESS;
        }

        $run = (string) ($this->option('run') ?: CarbonImmutable::now()->format('Y-m-d-His'));
        $this->info("Banc {$run} : ".count($plan->topics).' sujet(s), '.count($plan->configurations).' configuration(s).');
        [$records, $failed] = $this->play($run, $plan, $settings, $prepare, $write, $dollars);

        $this->table(
            ['Configuration', 'Articles', 'Cout moyen', 'Duree moyenne', 'Mots (EN)', 'Affirmations retirees'],
            array_map(static fn (array $row): array => [
                $row['name'], $row['articles'], $dollars->dollars($row['cost_microusd']), intdiv($row['duration_ms'], 1_000).' s', $row['words'], $row['removed_claims'],
            ], array_values($measures->summary($records))),
        );
        $this->line('Resultats : '.$storage->location($run));

        if ($failed > 0) {
            $this->warn("{$failed} sujet(s) ou article(s) n'ont pas abouti (voir le journal). Relancez avec --run={$run} pour les reprendre.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Chaque sujet, puis chaque configuration ; un echec est compte et le banc continue, sauf credit epuise.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function play(string $run, BlogBenchPlan $plan, BlogAiSettings $settings, PrepareBlogBenchTopicAction $prepare, RunBlogBenchArticleAction $write, BlogAiCostFormatter $dollars): array
    {
        $records = [];
        $failed = 0;

        foreach ($plan->topics as $topic) {
            try {
                $prepared = $prepare->execute($run, $topic, $plan, $settings);
                $this->line("{$topic->key} : sujet prepare, ".$dollars->dollars((int) $prepared['cost_microusd']).'.');
            } catch (Throwable $e) {
                $failed += 1 + count($plan->configurations);

                if ($this->stops($e)) {
                    break;
                }

                continue;
            }

            foreach ($plan->configurations as $configuration) {
                try {
                    $record = $write->execute($run, $topic, $prepared, $configuration, $plan, $settings);
                    $records[] = $record;
                    $this->line("{$topic->key} / {$configuration->key} : ".$dollars->dollars((int) $record['cost_microusd']).', '
                        .intdiv((int) $record['duration_ms'], 1_000).' s, '.$record['measures']['words']['style'].' mots.');
                } catch (Throwable $e) {
                    $failed++;

                    if ($this->stops($e)) {
                        break 2;
                    }
                }
            }
        }

        return [$records, $failed];
    }

    /** Dit l'echec a l'ecran ; vrai quand le banc doit s'arreter. */
    private function stops(Throwable $e): bool
    {
        if ($e instanceof BlogBenchException) {
            $this->error($e->getUserMessage());

            return $e->stopsBench;
        }

        Log::error('Unexpected error in the blog bench.', ['exception' => $e]);
        $this->error('Erreur inattendue, le detail est dans le journal. Le banc passe a la suite.');

        return false;
    }

    /** @param  array<string, mixed>  $forecast  BlogBenchForecaster::forecast() */
    private function showForecast(array $forecast, int $topics, BlogAiCostFormatter $dollars): void
    {
        $range = static fn (GenerationCostForecast $cost): string => $dollars->dollars($cost->lowMicrousd).' a '.$dollars->dollars($cost->highMicrousd);

        $this->line("Recherche partagee, par sujet : {$range($forecast['shared'])}. Image, par sujet : {$range($forecast['image'])}.");
        $this->table(
            ['Configuration', 'Un article (recherche et image comprises)', 'Plafond, marge comprise', 'Depasse ?'],
            array_map(static fn (array $row): array => [
                $row['configuration']->name, $range($row['article']), $dollars->dollars($row['limit_microusd']), $row['exceeds'] ? 'oui' : 'non',
            ], $forecast['configurations']),
        );
        $this->info("Banc complet ({$topics} sujet(s)) : {$range($forecast['total'])}.");
    }
}
