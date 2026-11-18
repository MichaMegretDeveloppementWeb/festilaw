<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Bench;

/**
 * Une configuration de modeles comparee par le banc : les modeles de la redaction, de la verification et des
 * versions.
 */
final readonly class BlogBenchConfiguration
{
    /**
     * @param  array<string, string>  $models  modele par reglage (writing, verification, translation)
     */
    public function __construct(
        public string $key,
        public string $name,
        public array $models,
    ) {}
}
