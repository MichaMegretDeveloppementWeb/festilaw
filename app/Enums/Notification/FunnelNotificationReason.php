<?php

declare(strict_types=1);

namespace App\Enums\Notification;

enum FunnelNotificationReason: string
{
    case CreatorSubmission = 'creator_submission';
    case ProSubmission = 'pro_submission';
    case ScaleAuditRequest = 'scale_audit_request';
    case PaymentReceived = 'payment_received';
    case ConsultationBooked = 'consultation_booked';
    case PackChanged = 'pack_changed';
    // Changement de pack apres paiement (SC12).
    case PackUpgradeStarted = 'pack_upgrade_started';
    case PackUpgraded = 'pack_upgraded';
    case PackDowngradeRequested = 'pack_downgrade_requested';

    public function subject(): string
    {
        return match ($this) {
            self::CreatorSubmission => __('New Creator Pack submission'),
            self::ProSubmission => __('New Pro Pack submission'),
            self::ScaleAuditRequest => __('New Scale Pack audit request'),
            self::PaymentReceived => __('Payment received'),
            self::ConsultationBooked => __('New Scale consultation booked'),
            self::PackChanged => __('Pack changed by the client'),
            self::PackUpgradeStarted => __('Switch to the Pro Pack started by the client'),
            self::PackUpgraded => __('Client switched to the Pro Pack (paid)'),
            self::PackDowngradeRequested => __('Switch back to the Creator Pack to review'),
        };
    }
}
