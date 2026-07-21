<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Renders the self-service dossier journey (Creator or Pro). The submission is resolved (from its
 * unguessable, expiring resume token) by the {dossier} route binding; the current step is derived
 * server-side from the submission status. A dossier that is already active (paid) is sent to its
 * "my file" space instead.
 */
final class StarterJourneyController extends Controller
{
    public function __invoke(Submission $dossier): View|RedirectResponse
    {
        abort_unless($dossier->type->hasOnlineJourney(), 404);

        if ($dossier->isActive()) {
            return redirect()->route('my-project', ['dossier' => $dossier->resume_token]);
        }

        return view('web.get-started.journey', ['submission' => $dossier]);
    }
}
