<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * La reponse d'un agent du pipeline editorial, avec ce que l'appel a consomme sur tous ses tours. La sortie
 * structuree est rendue telle quelle : un JSON que le modele a rate arrive vide (le SDK ne leve rien), et
 * c'est l'etape qui verifie ce qu'elle recoit.
 */
final readonly class BlogAgentReply
{
    public function __construct(
        public AgentResponse $response,
        public GenerationUsage $usage,
    ) {}

    /** @return array<string, mixed> */
    public function structured(): array
    {
        return $this->response instanceof StructuredAgentResponse ? $this->response->structured : [];
    }

    public function text(): string
    {
        return $this->response->text;
    }

    /**
     * Les pages citees par le modele pendant sa recherche, tous tours confondus, sans doublon d'adresse.
     *
     * @return list<array{url: string, title: string|null}>
     */
    public function citations(): array
    {
        $citations = collect($this->response->steps)
            ->flatMap(fn ($step) => $step->meta->citations ?? [])
            ->merge($this->response->meta->citations);

        return $citations
            ->filter(fn ($citation): bool => $citation instanceof UrlCitation && filled($citation->url))
            ->unique(fn (UrlCitation $citation): string => $citation->url)
            ->map(fn (UrlCitation $citation): array => ['url' => $citation->url, 'title' => $citation->title])
            ->values()
            ->all();
    }
}
