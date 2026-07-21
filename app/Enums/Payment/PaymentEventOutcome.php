<?php

declare(strict_types=1);

namespace App\Enums\Payment;

/**
 * Provider-agnostic outcome of a payment event (webhook) or a live status poll (checkStatus). Maps the
 * many provider event types (Stripe checkout.session.completed / async_payment_succeeded / _failed /
 * expired / charge.refunded ...) onto the small set of transitions our state machine understands. Kept
 * separate from PaymentStatus: an outcome is what the provider *tells* us, a status is what we *store*.
 */
enum PaymentEventOutcome
{
    /** Definitively paid (funds captured). */
    case Paid;

    /** Async method accepted, awaiting settlement (Klarna/Bancontact/MB WAY). */
    case Processing;

    /** Definitively failed (declined / async payment failed). */
    case Failed;

    /** Checkout session expired without payment (buyer abandoned). */
    case Expired;

    /** Refunded or charged back after a successful payment. */
    case Refunded;

    /** Nothing actionable (session still open, or an event we don't act on). */
    case Unresolved;
}
