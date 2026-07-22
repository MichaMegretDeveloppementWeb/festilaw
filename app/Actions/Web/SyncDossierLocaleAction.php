<?php

declare(strict_types=1);

namespace App\Actions\Web;

use App\Models\Submission;

/**
 * Aligns the dossier's stored locale with the visitor's CURRENT display language. Called on each load of
 * the self-service journey / Scale space: a language switch reloads the page, so the last language the
 * visitor actually used becomes the dossier language shown in the back-office. Idempotent (no-op when
 * unchanged) and defensive (ignores an unsupported locale · the display locale is validated upstream by
 * SetLocale, this is a belt-and-braces guard).
 */
final readonly class SyncDossierLocaleAction
{
    public function execute(Submission $submission): void
    {
        $locale = app()->getLocale();

        if ($submission->locale === $locale
            || ! in_array($locale, (array) config('festilaw.supported_locales'), true)) {
            return;
        }

        $submission->update(['locale' => $locale]);
    }
}
