<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Bench;

use App\Enums\Blog\Generation\BlogTopicKind;

/**
 * Un sujet du banc d'essai : sa cle (nom des fichiers de resultats), sa nature et le sujet impose, en anglais.
 */
final readonly class BlogBenchTopic
{
    public function __construct(
        public string $key,
        public BlogTopicKind $kind,
        public string $topic,
    ) {}
}
