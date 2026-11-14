<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGenerationStep;

/**
 * L'etape sur laquelle des generations ont echoue, pour le dire dans un e-mail (fin de lot, serie d'echecs).
 * Une requete pour toutes les generations demandees, jamais une par generation.
 */
final readonly class FailedBlogGenerationStepRepository
{
    /**
     * @param  list<int>  $generationIds
     * @return array<int, string> cle de l'etape en echec, par generation (absente si aucune etape n'a echoue)
     */
    public function stepByGeneration(array $generationIds): array
    {
        if ($generationIds === []) {
            return [];
        }

        return BlogGenerationStep::query()
            ->whereIn('blog_generation_id', $generationIds)
            ->where('status', BlogGenerationStepStatus::Failed->value)
            ->pluck('step', 'blog_generation_id')
            ->all();
    }
}
