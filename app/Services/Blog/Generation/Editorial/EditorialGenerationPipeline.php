<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Actions\Blog\Generation\Editorial\AssembleGeneratedBlogDraftAction;
use App\Actions\Blog\Generation\Editorial\GenerateBlogDraftImagesAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Services\Blog\Generation\Editorial\Steps\AssistedTranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\CategoryStep;
use App\Services\Blog\Generation\Editorial\Steps\FactSearchStep;
use App\Services\Blog\Generation\Editorial\Steps\FeaturedImageBriefStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchNotesStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchStep;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicScanStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicStep;
use App\Services\Blog\Generation\Editorial\Steps\TranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\VerificationStep;
use App\Services\Blog\Generation\Editorial\Steps\VersionCheckStep;
use App\Services\Blog\Generation\Editorial\Steps\WritingStep;
use Illuminate\Contracts\Container\Container;

/**
 * Le pipeline editorial (SC6, cahier des charges § 11) : recherche de pistes, choix du sujet, recherche sur
 * le web, dossier de faits, redaction, recherche des preuves (20/11), verification, relecture du style, choix
 * de la categorie (27/11, une fois l'article termine), versions francaise et espagnole, chacune controlee contre
 * le texte anglais, images, assemblage du brouillon. Chaque recherche web est une etape a elle seule : enregistree des qu'elle finit, elle n'est
 * jamais refaite par un nouvel essai de l'etape qui la met en forme. Les etapes de texte
 * sont des Services ; les images et l'assemblage, qui ecrivent, sont des Actions. La traduction assistee d'un
 * article ecrit a la main (type `translation`) tient en une etape. Une image principale demandee depuis un article
 * (type `image`, 24/11/2026) en tient deux : sa description, puis l'etape des images.
 */
final readonly class EditorialGenerationPipeline implements GenerationPipeline
{
    public function __construct(private Container $container) {}

    public function steps(BlogGenerationType $type): array
    {
        return match ($type) {
            BlogGenerationType::Article => [
                $this->container->make(TopicScanStep::class),
                $this->container->make(TopicStep::class),
                $this->container->make(ResearchNotesStep::class),
                $this->container->make(ResearchStep::class),
                $this->container->make(WritingStep::class),
                $this->container->make(FactSearchStep::class),
                $this->container->make(VerificationStep::class),
                $this->container->make(StyleStep::class),
                $this->container->make(CategoryStep::class),
                $this->container->make(TranslationStep::class, ['locale' => 'fr']),
                $this->container->make(VersionCheckStep::class, ['locale' => 'fr']),
                $this->container->make(TranslationStep::class, ['locale' => 'es']),
                $this->container->make(VersionCheckStep::class, ['locale' => 'es']),
                $this->container->make(GenerateBlogDraftImagesAction::class),
                $this->container->make(AssembleGeneratedBlogDraftAction::class),
            ],
            BlogGenerationType::Translation => [$this->container->make(AssistedTranslationStep::class)],
            BlogGenerationType::Image => [
                $this->container->make(FeaturedImageBriefStep::class),
                $this->container->make(GenerateBlogDraftImagesAction::class),
            ],
        };
    }
}
