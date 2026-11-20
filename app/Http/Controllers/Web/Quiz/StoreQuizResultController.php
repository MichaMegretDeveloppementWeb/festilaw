<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Quiz;

use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Analytics\AudienceEvent;
use App\Enums\Quiz\QuizOutcome;
use App\Http\Controllers\Controller;
use App\Models\QuizResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enregistre une reponse anonyme au quiz public. L'issue est re-derivee cote serveur a partir
 * des trois reponses : on ne fait pas confiance au calcul du client.
 */
final class StoreQuizResultController extends Controller
{
    public function __invoke(Request $request, AudienceTracker $audience): JsonResponse
    {
        $data = $request->validate([
            'q1_based_outside_eu' => ['required', 'boolean'],
            'q2_sells_to_eu' => ['required', 'boolean'],
            'q3_sells_restricted' => ['required', 'boolean'],
        ]);

        $outcome = QuizOutcome::fromAnswers(
            (bool) $data['q1_based_outside_eu'],
            (bool) $data['q2_sells_to_eu'],
            (bool) $data['q3_sells_restricted'],
        );

        QuizResult::create([
            'q1_based_outside_eu' => $data['q1_based_outside_eu'],
            'q2_sells_to_eu' => $data['q2_sells_to_eu'],
            'q3_sells_restricted' => $data['q3_sells_restricted'],
            'outcome' => $outcome,
            'locale' => app()->getLocale(),
        ]);

        $audience->record(AudienceEvent::quizResult($outcome));

        return response()->json(status: 201);
    }
}
