<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Scale\ConfirmScaleAuditAction;
use App\Actions\Web\SyncDossierLocaleAction;
use App\Enums\Submission\SubmissionType;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\Web\Scale\ScaleSpaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The client's SCALE space (magic link, capability URL). The hub for a SCALE dossier: pay the 75 EUR
 * audit, then book the 45-minute consultation. Reached by its {dossier} token binding. On return from the
 * checkout it confirms the audit server-side (the signed webhook stays the source of truth in production).
 */
final class ScaleSpaceController extends Controller
{
    public function __construct(
        private readonly ConfirmScaleAuditAction $confirmAudit,
        private readonly ScaleSpaceService $space,
        private readonly SyncDossierLocaleAction $syncLocale,
    ) {}

    public function __invoke(Request $request, Submission $dossier): View
    {
        abort_unless($dossier->type === SubmissionType::Scale, 404);

        // La derniere langue d'affichage utilisee devient celle du dossier (visible au back-office).
        $this->syncLocale->execute($dossier);

        // Retour du checkout : on confirme la synchrone (le webhook reste le filet cote serveur en prod ;
        // en local il ne peut pas joindre le site).
        if ($request->boolean('audit_return')) {
            $this->confirmAudit->execute($dossier);
        }

        return view('web.scale-space', ['space' => $this->space->spaceFor($dossier)]);
    }
}
