<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Actions\Blog\Generation\Editorial\AssembleGeneratedBlogDraftAction;
use App\Actions\Blog\Generation\Editorial\GenerateBlogDraftImagesAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Services\Blog\Generation\Editorial\Steps\AssistedTranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchStep;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicStep;
use App\Services\Blog\Generation\Editorial\Steps\TranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\VerificationStep;
use App\Services\Blog\Generation\Editorial\Steps\WritingStep;
use Illuminate\Contracts\Container\Container;

/**
 * Le pipeline editorial (SC6, cahier des charges § 11) : sujet, recherche, redaction, verification, relecture
 * du style, traductions francaise et espagnole, images, assemblage du brouillon. Les etapes de texte sont des
 * Services ; les images et l'assemblage, qui ecrivent, sont des Actions. La traduction assistee d'un article
 * ecrit a la main (type `translation`) tient en une etape.
 */
final readonly class EditorialGenerationPipeline implements GenerationPipeline
{
    public function __construct(private Container $container) {}

    public function steps(BlogGenerationType $type): array
    {
        return match ($type) {
            BlogGenerationType::Article => [
                $this->container->make(TopicStep::class),
                $this->container->make(ResearchStep::class),
                $this->container->make(WritingStep::class),
                $this->container->make(VerificationStep::class),
                $this->container->make(StyleStep::class),
                $this->container->make(TranslationStep::class, ['locale' => 'fr']),
                $this->container->make(TranslationStep::class, ['locale' => 'es']),
                $this->container->make(GenerateBlogDraftImagesAction::class),
                $this->container->make(AssembleGeneratedBlogDraftAction::class),
            ],
            BlogGenerationType::Translation => [$this->container->make(AssistedTranslationStep::class)],
        };
    }
}
