<?php

declare(strict_types=1);

namespace App\Ai\Agents\Blog;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use ReflectionClass;

/**
 * Socle des agents du pipeline editorial du blog (SC6). Un agent ne s'appelle jamais directement : les etapes
 * passent par `BlogAgentCaller`, qui lui pose, le temps d'un appel, le releve de consommation et les options
 * de la requete.
 *
 * Dossier `app/Ai/Agents` : convention du SDK (`php artisan make:agent`), accepte par Micha avec le plan de SC6.
 */
abstract class BlogAgent implements Agent, HasMiddleware, HasProviderOptions
{
    use Promptable;

    /** Limite comptee pour la reservation quand un agent n'en fixe pas (tous ceux du pipeline en fixent une). */
    private const DEFAULT_MAX_TOKENS = 4096;

    /** @var list<object> */
    private array $stepMiddleware = [];

    /** @var array<string, mixed> */
    private array $requestOptions = [];

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

    /** @param  array<string, mixed>  $options */
    public function withRequestOptions(array $options): static
    {
        $this->requestOptions = $options;

        return $this;
    }

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        return $this->requestOptions;
    }

    /** Le SDK n'expose pas la limite d'un agent : on relit son attribut `MaxTokens`. Elle compte la reflexion. */
    public function maxOutputTokens(): int
    {
        $attribute = (new ReflectionClass($this))->getAttributes(MaxTokens::class)[0] ?? null;

        return $attribute === null ? self::DEFAULT_MAX_TOKENS : (int) $attribute->newInstance()->value;
    }
}
