<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;

/**
 * Une traduction assistee vue du formulaire qui l'a demandee : son statut, sa demande et, une fois finie,
 * ce que son etape a rendu. Une requete (sous-requete pour la sortie de l'etape).
 */
final class BlogTranslationRequestRepository
{
    /** @return object{status: string, payload: string|null, output: string|null}|null */
    public function find(int $generationId): ?object
    {
        return BlogGeneration::query()->toBase()
            ->where('blog_generations.id', $generationId)
            ->where('type', BlogGenerationType::Translation->value)
            ->select(['status', 'payload'])
            ->addSelect(['output' => BlogGenerationStep::query()->toBase()
                ->select('output')
                ->whereColumn('blog_generation_id', 'blog_generations.id')
                ->where('step', 'translation')
                ->where('status', BlogGenerationStepStatus::Completed->value)
                ->limit(1)])
            ->first();
    }
}
