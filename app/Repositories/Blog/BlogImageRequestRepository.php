<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use App\Models\BlogImage;
use Illuminate\Database\Eloquent\Builder;

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
     * Les pages de la banque des photos deja obtenues pour un article (24/11/2026), pour qu'une nouvelle recherche ne
     * reprenne pas l'une d'elles. 1 requete.
     *
     * @return list<string>
     */
    public function obtainedCreditUrls(int $postId): array
    {
        return BlogImage::query()
            ->whereIn('generation_id', BlogGeneration::query()->select('id')
                ->where(fn (Builder $generation) => $generation->where('blog_post_id', $postId)->orWhere('source_blog_post_id', $postId)))
            ->whereNotNull('credit_url')
            ->distinct()
            ->pluck('credit_url')
            ->all();
    }

    /**
     * La derniere demande d'image d'un article que le formulaire n'a pas encore recue : en cours, il l'attend de
     * nouveau apres un rechargement ; finie pendant l'absence, il la recoit au retour (Micha, 24/11/2026). Une
     * demande arretee a la main ne se remet pas.
     *
     * @return object{id: int, status: string, payload: string|null}|null
     */
    public function undeliveredForPost(int $postId): ?object
    {
        return BlogGeneration::query()->toBase()
            ->where('type', BlogGenerationType::Image->value)
            ->where('source_blog_post_id', $postId)
            ->whereNull('delivered_at')
            ->where('status', '!=', BlogGenerationStatus::Cancelled->value)
            ->orderByDesc('id')
            ->first(['id', 'status', 'payload']);
    }
}
