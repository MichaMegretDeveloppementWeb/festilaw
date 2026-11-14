<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Exceptions\BaseAppException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Met en file une generation d'article depuis la ligne de commande, comme le fait le bouton du back-office
 * (QueueBlogGenerationAction, memes refus). Elle n'execute rien : le worker la prend en charge. Utile pour
 * une verification apres deploiement, ou quand le back-office n'est pas accessible.
 */
final class QueueBlogGeneration extends Command
{
    protected $signature = 'festilaw:blog-generate
        {--topic= : Sujet impose (facultatif)}
        {--instructions= : Consignes pour cette generation (facultatif)}';

    protected $description = 'Met en file une generation d\'article du blog (traitee par le worker).';

    public function handle(QueueBlogGenerationAction $queue): int
    {
        try {
            $generationId = $queue->execute(new QueueBlogGenerationData(
                topicHint: $this->option('topic'),
                instructions: $this->option('instructions'),
            ));
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->error($e->getUserMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            Log::error('Blog generation could not be queued from the command line.', ['exception' => $e]);
            $this->error('La generation n\'a pas pu etre mise en file (voir le journal).');

            return self::FAILURE;
        }

        $this->info("Generation n° {$generationId} mise en file.");

        return self::SUCCESS;
    }
}
