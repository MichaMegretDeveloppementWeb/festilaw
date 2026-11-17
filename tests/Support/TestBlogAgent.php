<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Ai\Agents\Blog\BlogAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Agent du blog pour les tests du passage vers les modeles : il ne fait rien de lui-meme et se simule par
 * `TestBlogAgent::fake([...])`. Son outil local permet de simuler un appel en plusieurs tours.
 */
final class TestBlogAgent extends BlogAgent implements HasTools
{
    public function instructions(): string
    {
        return 'Test agent.';
    }

    /** @return list<Tool> */
    public function tools(): iterable
    {
        return [new class implements Tool
        {
            public function name(): string
            {
                return 'ping';
            }

            public function description(): Stringable|string
            {
                return 'Returns the word ok.';
            }

            public function handle(Request $request): Stringable|string
            {
                return 'ok';
            }

            /** @return array<string, mixed> */
            public function schema(JsonSchema $schema): array
            {
                return [];
            }
        }];
    }
}
