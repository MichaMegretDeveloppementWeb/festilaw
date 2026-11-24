<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;

/**
 * Une image principale demandee depuis un article (24/11/2026), vue du formulaire qui l'a demandee : son statut,
 * sa demande et, une fois finie, ce que l'etape des images a rendu. Une requete (sous-requete pour la sortie de
 * l'etape). Meme forme que BlogTranslationRequestRepository.
 */
final class BlogImageRequestRepository
{
    /** @return object{status: string, payload: string|null, output: string|null}|null */
    public function find(int $generationId): ?object
    {
        return BlogGeneration::query()->toBase()
            ->where('blog_generations.id', $generationId)
            ->where('type', BlogGenerationType::Image->value)
            ->select(['status', 'payload'])
            ->addSelect(['output' => BlogGenerationStep::query()->toBase()
                ->select('output')
                ->whereColumn('blog_generation_id', 'blog_generations.id')
                ->where('step', 'images')
                ->where('status', BlogGenerationStepStatus::Completed->value)
                ->limit(1)])
            ->first();
    }

    /**
     * La demande d'image encore en attente ou en cours pour un article, la plus recente : le formulaire la retrouve
     * apres un rechargement.
     *
     * @return object{id: int, payload: string|null}|null
     */
    public function activeForPost(int $postId): ?object
    {
        return BlogGeneration::query()->toBase()
            ->where('type', BlogGenerationType::Image->value)
            ->whereIn('status', BlogGenerationStatus::activeValues())
            ->where('payload->image_request->post_id', $postId)
            ->orderByDesc('id')
            ->first(['id', 'payload']);
    }
}
