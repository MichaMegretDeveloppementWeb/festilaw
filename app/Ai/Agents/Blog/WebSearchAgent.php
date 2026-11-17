<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Premier temps du sujet et de la recherche : l'agent cherche sur le web, puis rend ses notes en texte libre,
 * avec les adresses de ses sources. Il n'a pas de sortie structuree : l'essai reel du 17/11 a montre qu'a
 * travers OpenRouter, un modele qui cherche sur le web ignore le format JSON demande. Un second agent, sans
 * outil, met ensuite ces notes en forme.
 *
 * Il ne lit pas de page entiere : au premier essai reel, la lecture de pages (texte complet du reglement
 * compris) a porte la recherche a 1,3 million de jetons et 3 $ pour un article. Les extraits rendus par la
 * recherche suffisent aux notes.
 */
#[MaxSteps(12)]
#[MaxTokens(8000)]
final class WebSearchAgent extends BlogAgent implements HasTools
{
    public function __construct(
        string $instructions = '',
        private readonly int $searches = 5,
    ) {
        parent::__construct($instructions);
    }

    public function tools(): iterable
    {
        return [(new WebSearch)->max($this->searches)];
    }
}
