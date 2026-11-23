<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\ProductionSafetyService;
use Illuminate\Console\Command;

/**
 * Checklist go-live : verifie que la configuration courante est apte a la production (aucun faux
 * prestataire, pas de mail simule, pas de debug, cles presentes...). Code de sortie non nul s'il reste
 * des manquements. deploy.sh la lance apres la mise en cache de la configuration et finit en echec
 * si elle echoue, le site restant en ligne (audit du 23/11). Les rappels (cle OpenRouter pas encore
 * collee dans les reglages) s'affichent sans la faire echouer.
 */
final class CheckProductionCommand extends Command
{
    protected $signature = 'festilaw:check-production';

    protected $description = 'Verifie que la configuration est prete pour la production (checklist go-live).';

    public function handle(ProductionSafetyService $safety): int
    {
        $violations = $safety->violations();

        foreach ($safety->reminders() as $reminder) {
            $this->warn($reminder);
        }

        if ($violations === []) {
            $this->info('Configuration prete pour la production.');

            return self::SUCCESS;
        }

        $this->error('Configuration NON prete pour la production ('.count($violations).') :');
        foreach ($violations as $violation) {
            $this->line('  · '.$violation);
        }

        return self::FAILURE;
    }
}
