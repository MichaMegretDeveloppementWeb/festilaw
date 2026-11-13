<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Textes modifiables d'une image deja enregistree : ses descriptions et son credit.
 */
final readonly class UpdateBlogImageData
{
    /**
     * @param  array<string, string>  $alt  description par langue
     */
    public function __construct(
        public array $alt,
        public ?string $creditAuthor = null,
        public ?string $creditUrl = null,
        public ?string $creditLicense = null,
    ) {}
}
