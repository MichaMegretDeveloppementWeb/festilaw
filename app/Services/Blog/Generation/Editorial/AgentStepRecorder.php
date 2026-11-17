<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use Closure;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;

/**
 * Intermediaire de tour d'un agent du SDK : garde la reponse de chaque tour, avec sa reponse brute, des
 * qu'elle arrive. Le SDK ne garde a la fin que la reponse brute du dernier tour ; c'est ici qu'on retrouve le
 * cout de tous les tours, y compris quand un tour suivant echoue. Un releve par appel.
 */
final class AgentStepRecorder
{
    /** @var list<StepResponse> */
    private array $responses = [];

    /** @param  Closure(PendingStep): StepResult  $next */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        return $next($step)->then(function (StepResponse $response): void {
            $this->responses[] = $response;
        });
    }

    /** @return list<StepResponse> */
    public function responses(): array
    {
        return $this->responses;
    }
}
