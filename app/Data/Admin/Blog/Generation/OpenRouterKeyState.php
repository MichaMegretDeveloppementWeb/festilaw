<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Generation;

/**
 * Ce que l'ecran dit de la cle OpenRouter des reglages : son debut et sa fin (jamais la cle entiere), si elle
 * permet de generer, la phrase de la section des reglages et le libelle court du tableau de bord.
 */
final readonly class OpenRouterKeyState
{
    public function __construct(
        public ?string $masked,
        public bool $usable,
        public bool $needsAttention,
        public string $sentence,
        public string $label,
    ) {}
}
