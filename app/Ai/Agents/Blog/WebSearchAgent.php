<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Cherche sur le web et rend des notes en texte libre. Pas de sortie structuree : a travers OpenRouter, un
 * modele qui cherche sur le web ignore le format JSON demande (essai reel du 17/11). Pas de lecture de page
 * entiere : au premier essai reel, elle a porte un article a 1,3 million de jetons et 3 $.
 *
 * Limite de longueur : 900 mots de notes (environ 1 300 jetons) plus une reflexion faible (20 % de la
 * limite), avec une marge de deux fois.
 */
#[MaxTokens(4000)]
final class WebSearchAgent extends BlogAgent implements HasTools
{
    /** @param  array{engine?: string, max_results?: int, max_characters?: int}  $searchOptions */
    public function __construct(
        string $instructions = '',
        private readonly int $searches = 5,
        private readonly array $searchOptions = [],
    ) {
        parent::__construct($instructions);
    }

    public function tools(): iterable
    {
        $options = array_filter([
            'engine' => $this->searchOptions['engine'] ?? null,
            'max_results' => $this->searchOptions['max_results'] ?? null,
            'max_characters' => $this->searchOptions['max_characters'] ?? null,
            'max_total_results' => isset($this->searchOptions['max_results']) ? $this->searches * (int) $this->searchOptions['max_results'] : null,
        ], static fn (mixed $value): bool => $value !== null);

        return [(new WebSearch)->max($this->searches)->withProviderOptions($options)];
    }

    public function searches(): int
    {
        return $this->searches;
    }

    /** Sans borne d'extraits, on compte ce qu'a pese une recherche native le 17/11. */
    public function tokensPerSearch(): int
    {
        if (! isset($this->searchOptions['max_results'], $this->searchOptions['max_characters'])) {
            return 25_000;
        }

        return intdiv((int) $this->searchOptions['max_results'] * (int) $this->searchOptions['max_characters'], 3);
    }
}
