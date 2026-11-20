<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Blog\Generation\RecordBlogGenerationHealthAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verification quotidienne des services de la generation d'articles (RecordBlogGenerationHealthAction) : cle et
 * modeles chez OpenRouter, Pixabay, file des generations, date de relecture de la reference des regles de l'UE.
 * Aucun appel a un modele, rien de paye. Rejouable : chaque passage remplace le resultat du precedent.
 */
final class CheckBlogGenerationHealth extends Command
{
    protected $signature = 'festilaw:blog-health-check';

    protected $description = 'Verifie les services de la generation d\'articles (OpenRouter, Pixabay, file, reference des regles de l\'UE).';

    public function handle(RecordBlogGenerationHealthAction $record): int
    {
        try {
            $health = $record->execute();
        } catch (Throwable $e) {
            Log::error('Blog generation health check could not run.', ['exception' => $e]);
            $this->error('La verification des services de la generation a echoue (voir le journal).');

            return self::FAILURE;
        }

        if ($health->isHealthy()) {
            $this->info('Services de la generation verifies : aucun probleme.');

            return self::SUCCESS;
        }

        foreach ($health->problems as $problem) {
            $this->warn($problem);
        }

        return self::FAILURE;
    }
}
