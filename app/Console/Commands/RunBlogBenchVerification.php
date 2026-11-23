<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\Bench\RunBlogBenchVerificationAction;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Banc de verification (SC7) : rejoue la seule etape de verification, avec les modeles demandes, sur le texte
 * redige des articles d'un banc deja joue. `--dry` donne le cout estime, sans aucun appel. Un article qui echoue
 * est journalise et le banc passe au suivant ; seul un credit epuise l'arrete. Une reprise ne repaie pas une
 * verification deja ecrite. Outil lance a la main, en local.
 */
final class RunBlogBenchVerification extends Command
{
    protected $signature = 'festilaw:blog-bench-verify
        {run : Nom du banc dont les articles sont verifies}
        {--model=* : Modeles de verification a essayer (identifiants du catalogue)}
        {--article=* : Articles a verifier (sujet--configuration), tous par defaut}
        {--dry : Donne le cout estime, sans aucun appel}';

    protected $description = 'Banc de verification : rejoue la verification des articles d\'un banc avec d\'autres modeles.';

    public function handle(
        RunBlogBenchVerificationAction $verify,
        BlogBenchFileStorage $storage,
        BlogAiSettingsService $settingsService,
        BlogGenerationCostForecaster $forecaster,
        BlogAiCostFormatter $dollars,
    ): int {
        // Outil local : lance sur le serveur, il appellerait des modeles payants sur le credit de Festilaw (audit du 23/11).
        if (app()->isProduction()) {
            $this->error("Le banc d'essai ne tourne qu'en local, jamais en production.");

            return self::FAILURE;
        }

        $run = (string) $this->argument('run');
        $models = array_values(array_map(strval(...), (array) $this->option('model')));
        $known = $storage->articleKeys($run);
        $articles = (array) $this->option('article') === [] ? $known : array_values(array_map(strval(...), (array) $this->option('article')));
        $unknown = array_values(array_diff($articles, $known));

        if ($models === [] || $articles === [] || $unknown !== []) {
            $this->error($unknown !== [] ? BlogBenchException::unknownKeys($unknown)->getUserMessage() : 'Indiquez au moins un modele (--model) et un banc qui a des articles.');

            return self::FAILURE;
        }

        if ($this->option('dry')) {
            $none = new GenerationImageOptions(BlogAiImageMode::None, 1);
            $low = 0;
            $high = 0;

            foreach ($models as $model) {
                $forecast = $forecaster->forecast(['verification' => $model], 1_000, $none, true, ['verification']);
                $low += $forecast->lowMicrousd * count($articles);
                $high += $forecast->highMicrousd * count($articles);
                $this->line("{$model} : ".$dollars->dollars($forecast->lowMicrousd).' a '.$dollars->dollars($forecast->highMicrousd).' par article.');
            }

            $this->info(count($articles).' article(s), '.count($models).' modele(s) : '.$dollars->dollars($low).' a '.$dollars->dollars($high).'.');

            return self::SUCCESS;
        }

        $settings = $settingsService->current();
        $failed = 0;
        $spent = 0;

        foreach ($models as $model) {
            foreach ($articles as $article) {
                try {
                    $record = $verify->execute($run, $article, $model, $settings);
                    $spent += (int) $record['step']['cost_microusd'];
                    $this->line("{$article} / {$model} : ".$dollars->dollars((int) $record['step']['cost_microusd']).', '
                        .count((array) $record['removed_claims']).' retrait(s), '.$record['words']['before'].' -> '.$record['words']['after'].' mots.');
                } catch (BlogBenchException $e) {
                    $failed++;
                    $this->error($e->getUserMessage());

                    if ($e->stopsBench) {
                        break 2;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('Unexpected error in the blog verification bench.', ['exception' => $e]);
                    $this->error('Erreur inattendue, le detail est dans le journal. Le banc passe a la suite.');
                }
            }
        }

        $this->info('Depense de ce passage (verifications deja ecrites comprises) : '.$dollars->dollars($spent).'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
