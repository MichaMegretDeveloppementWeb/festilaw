<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Quiz;

use App\Enums\Quiz\QuizOutcome;
use App\Http\Controllers\Controller;
use App\Models\QuizResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enregistre une reponse au quiz public d'eligibilite (anonyme, cf. CDC 6). Appele une fois en fin de
 * quiz par le composant Alpine. L'issue est RE-derivee cote serveur a partir des trois reponses : on
 * ne fait pas confiance au calcul du client. Permet a l'equipe de suivre les reponses dans le back-office.
 */
final class StoreQuizResultController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q1_based_outside_eu' => ['required', 'boolean'],
            'q2_sells_to_eu' => ['required', 'boolean'],
            'q3_sells_restricted' => ['required', 'boolean'],
        ]);

        QuizResult::create([
            'q1_based_outside_eu' => $data['q1_based_outside_eu'],
            'q2_sells_to_eu' => $data['q2_sells_to_eu'],
            'q3_sells_restricted' => $data['q3_sells_restricted'],
            'outcome' => QuizOutcome::fromAnswers(
                (bool) $data['q1_based_outside_eu'],
                (bool) $data['q2_sells_to_eu'],
                (bool) $data['q3_sells_restricted'],
            ),
            'locale' => app()->getLocale(),
        ]);

        return response()->json(status: 201);
    }
}
