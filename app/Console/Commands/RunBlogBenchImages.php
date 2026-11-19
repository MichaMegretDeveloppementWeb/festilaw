<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\Bench\RunBlogBenchImagesAction;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Banc d'images (C9) : redonne le prompt de l'image deja generee pour chaque sujet d'un banc deja joue a
 * d'autres modeles d'image, sans reecrire les articles. `--dry` donne le cout estime, sans aucun appel. Une
 * image qui echoue est journalisee et le banc passe a la suivante ; seul un credit epuise l'arrete. Une reprise
 * ne repaie pas une image deja faite. Outil lance a la main, en local.
 */
final class RunBlogBenchImages extends Command
{
    protected $signature = 'festilaw:blog-bench-images
        {run : Nom du banc dont les prompts d\'image sont repris}
        {--model=* : Modeles d\'image a essayer (identifiants du catalogue)}
        {--topic=* : Sujets du banc, tous ceux qui ont une image par defaut}
        {--dry : Donne le cout estime, sans aucun appel}';

    protected $description = 'Banc d\'images : redonne les prompts d\'image d\'un banc a d\'autres modeles.';

    public function handle(RunBlogBenchImagesAction $images, BlogBenchFileStorage $storage, BlogAiModelCatalog $catalog, BlogAiCostFormatter $dollars): int
    {
        $run = (string) $this->argument('run');
        $models = array_values(array_map(strval(...), (array) $this->option('model')));
        $known = array_values(array_unique(array_map(static fn (string $key): string => explode('--', $key)[0], $storage->articleKeys($run))));
        $topics = (array) $this->option('topic') === [] ? $known : array_values(array_map(strval(...), (array) $this->option('topic')));
        $unknown = array_values(array_diff($topics, $known));
        $unknownModels = array_values(array_diff($models, $catalog->idsFor(BlogAiModelCatalog::IMAGE_SETTING)));

        if ($models === [] || $topics === [] || $unknown !== [] || $unknownModels !== []) {
            $this->error(match (true) {
                $unknown !== [] => BlogBenchException::unknownKeys($unknown)->getUserMessage(),
                $unknownModels !== [] => 'Modele d\'image absent du catalogue : '.implode(', ', $unknownModels).'.',
                default => 'Indiquez au moins un modele (--model) et un banc qui a des articles.',
            });

            return self::FAILURE;
        }

        if ($this->option('dry')) {
            $low = 0;
            $high = 0;

            foreach ($models as $model) {
                [$imageLow, $imageHigh] = $catalog->imagePriceMicrousd($model);
                $low += $imageLow * count($topics);
                $high += $imageHigh * count($topics);
                $this->line("{$model} : ".$dollars->dollars($imageLow).' a '.$dollars->dollars($imageHigh).' par image.');
            }

            $this->info(count($topics).' sujet(s), '.count($models).' modele(s) : '.$dollars->dollars($low).' a '.$dollars->dollars($high).'.');

            return self::SUCCESS;
        }

        $failed = 0;
        $spent = 0;

        foreach ($models as $model) {
            foreach ($topics as $topic) {
                try {
                    $record = $images->execute($run, $topic, $model);
                    $spent += (int) $record['cost_microusd'];
                    $this->line("{$topic} / {$model} : ".$dollars->dollars((int) $record['cost_microusd']).', '
                        .round(((int) $record['duration_ms']) / 1_000).' s, '.$record['width'].' x '.$record['height'].'.');
                } catch (BlogBenchException $e) {
                    $failed++;
                    $this->error($e->getUserMessage());

                    if ($e->stopsBench) {
                        break 2;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('Unexpected error in the blog image bench.', ['exception' => $e]);
                    $this->error('Erreur inattendue, le detail est dans le journal. Le banc passe a la suite.');
                }
            }
        }

        $this->info('Depense de ce passage (images deja faites comprises) : '.$dollars->dollars($spent).'. Images dans '.$storage->location($run).DIRECTORY_SEPARATOR.'images.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
