<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

/**
 * Ce que le controle des photos dit d'une photo de la banque : si elle convient a l'article, pourquoi, et ce
 * qu'elle montre vraiment, pour son texte alternatif dans chaque langue. Avec ce que l'appel a coute.
 */
final readonly class StockPhotoVerdict
{
    /** @param  array<string, string>  $alt  ce que la photo montre, par langue (en, fr, es) */
    public function __construct(
        public bool $fits,
        public string $reason,
        public array $alt,
        public GenerationUsage $usage,
    ) {}
}
