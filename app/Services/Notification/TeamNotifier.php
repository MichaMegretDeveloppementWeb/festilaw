<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends internal (team) notifications to Festilaw. A failed notification is a peripheral side effect:
 * it is logged but never allowed to break the user's flow (cf. gestion-erreurs, erreurs partielles
 * non bloquantes). Sent synchronously after commit by the calling Action. Returns whether the send
 * succeeded, so a caller can gate an idempotence marker on it (e.g. the yearly renewal digest).
 *
 * Not final: a couple of tests substitute a double to simulate a failed notification.
 */
class TeamNotifier
{
    public function notify(Mailable $mailable): bool
    {
        try {
            Mail::to(config('festilaw.notification_email'))->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('Team notification failed to send.', [
                'exception' => $e,
                'mailable' => $mailable::class,
            ]);

            return false;
        }
    }
}
