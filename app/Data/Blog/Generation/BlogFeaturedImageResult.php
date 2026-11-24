<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Ou en est une image principale demandee depuis un article : en cours, prete (l'image trouvee ou creee, libre
 * tant que l'article n'est pas enregistre), ou sans image, avec ce qui s'est passe : aucune photo convenable,
 * banque injoignable, ou panne.
 */
final readonly class BlogFeaturedImageResult
{
    public const NONE_FIT = 'none_fit';

    public const BANK_UNAVAILABLE = 'bank_unavailable';

    public const FAILED = 'failed';

    private function __construct(
        public string $state,
        public ?int $imageId = null,
        public ?string $cause = null,
        public ?string $mode = null,
    ) {}

    public static function pending(): self
    {
        return new self('pending');
    }

    public static function ready(int $imageId, string $mode): self
    {
        return new self('ready', $imageId, mode: $mode);
    }

    public static function missing(string $cause, ?string $mode = null): self
    {
        return new self('missing', cause: $cause, mode: $mode);
    }

    public function isPending(): bool
    {
        return $this->state === 'pending';
    }

    public function isReady(): bool
    {
        return $this->state === 'ready';
    }
}
