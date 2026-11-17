<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

/**
 * Socle des agents du pipeline editorial du blog (SC6). Un agent ne s'appelle jamais directement : les etapes
 * passent par `BlogAgentCaller`, qui fixe le fournisseur, le modele et le delai, et pose ici, le temps d'un
 * appel, l'intermediaire qui releve la consommation de chaque tour de l'agent (un agent qui cherche sur le web
 * fait plusieurs tours, chacun facture).
 *
 * Dossier `app/Ai/Agents` : convention du SDK (`php artisan make:agent`), accepte par Micha avec le plan de SC6.
 */
abstract class BlogAgent implements Agent, HasMiddleware
{
    use Promptable;

    /** @var list<object> */
    private array $stepMiddleware = [];

    /** @param  string  $instructions  les consignes, composees par l'etape depuis `resources/prompts/blog` */
    public function __construct(private readonly string $instructions = '') {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /** Remplace les intermediaires de tour : un seul releve par appel. */
    public function observeStepsWith(object ...$middleware): static
    {
        $this->stepMiddleware = array_values($middleware);

        return $this;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return $this->stepMiddleware;
    }
}
