<?php

declare(strict_types=1);

namespace App\Exceptions\Web;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A dossier link (resume token) that no longer resolves: replaced by a newer emailed link, expired, or never
 * valid. Still a not-found (not reported, like any other), but the visitor is sent to an explanatory page
 * offering to email a fresh link, instead of the generic "page not found".
 *
 * That page has its own fixed address and is not rendered here, at the dead link's address: on every round
 * trip, Livewire replays the route of the page a component was rendered on, bindings included. Rendered at
 * the dead link's address, the page's form would hit this exception again and could never send anything.
 */
final class DossierLinkInvalidException extends NotFoundHttpException
{
    public function render(Request $request): Response
    {
        return redirect()->route('link-expired');
    }
}
