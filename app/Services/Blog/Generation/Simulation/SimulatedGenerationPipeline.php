<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Simulation;

use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Enums\Blog\Generation\BlogGenerationType;

/**
 * Pipeline factice : les memes etapes que le pipeline editorial, dans le meme ordre, mais sans appel a un
 * modele et sans article produit. Reserve au developpement et aux essais ; il est refuse en production par
 * BlogGenerationServiceProvider.
 */
final readonly class SimulatedGenerationPipeline implements GenerationPipeline
{
    private const STEPS = [
        'article' => ['topic', 'research', 'writing', 'verification', 'style', 'translation_fr', 'translation_es', 'images', 'assembly'],
        'translation' => ['translation'],
        'image' => ['image_brief', 'images'],
    ];

    public function __construct(
        private int $secondsPerStep,
        private int $costMicrousdPerStep,
        private ?string $failStep,
        private bool $stopWithoutTopic,
    ) {}

    public function steps(BlogGenerationType $type): array
    {
        return array_map(
            fn (string $key): SimulatedGenerationStep => new SimulatedGenerationStep(
                key: $key,
                seconds: $this->secondsPerStep,
                costMicrousd: $this->costMicrousdPerStep,
                fails: $key === $this->failStep,
                stopsWithoutTopic: $key === 'topic' && $this->stopWithoutTopic,
            ),
            self::STEPS[$type->value],
        );
    }
}
