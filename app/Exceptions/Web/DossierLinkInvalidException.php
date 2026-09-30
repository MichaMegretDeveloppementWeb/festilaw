<?php

declare(strict_types=1);

namespace App\Exceptions\Web;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A dossier link (resume token) that no longer resolves: replaced by a newer emailed link, expired, or never
 * valid. Still a 404 (not reported, like any not-found), but rendered as an explanatory page offering to
 * email a fresh link, instead of the generic "page not found".
 */
final class DossierLinkInvalidException extends NotFoundHttpException
{
    public function render(Request $request): Response
    {
        return response()->view('web.dossier-link-invalid', [], 404);
    }
}
