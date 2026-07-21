<?php

declare(strict_types=1);

namespace App\Enums\Contract;

/**
 * Provider-agnostic outcome of a signature event (webhook) or a live status poll (checkStatus). Maps the
 * provider document statuses (SignWell Completed / Declined / Canceled / Expired ...) onto the small set
 * of transitions our state machine understands. Kept separate from SignatureStatus: an outcome is what
 * the provider *tells* us, a status is what we *store*.
 */
enum SignatureEventOutcome
{
    /** The document was fully signed. */
    case Signed;

    /** The signer declined, or the sender canceled the document. */
    case Declined;

    /** The document expired without being signed. */
    case Expired;

    /** Nothing actionable (document still out for signature, or an event we don't act on). */
    case Unresolved;
}
