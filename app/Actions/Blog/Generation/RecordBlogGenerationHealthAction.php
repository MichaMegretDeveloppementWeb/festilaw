<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Data\Blog\Generation\Health\BlogGenerationHealth;
use App\Repositories\Blog\BlogGenerationHealthRepository;
use App\Services\Blog\Generation\Health\BlogGenerationHealthCheck;
use Illuminate\Support\Facades\Log;

/**
 * La verification quotidienne des services de la generation : garde son resultat (pour le tableau de bord, et
 * les prix du jour des modeles pour l'estimation), et journalise chaque probleme en erreur, un message par
 * probleme, pour que le rapport d'erreurs du matin les porte au prestataire technique tant qu'ils durent.
 */
final readonly class RecordBlogGenerationHealthAction
{
    public function __construct(
        private BlogGenerationHealthCheck $check,
        private BlogGenerationHealthRepository $health,
    ) {}

    public function execute(): BlogGenerationHealth
    {
        $health = $this->check->run();
        $this->health->store($health);

        foreach ($health->problems as $problem) {
            Log::error('Blog generation health check: '.$problem);
        }

        return $health;
    }
}
