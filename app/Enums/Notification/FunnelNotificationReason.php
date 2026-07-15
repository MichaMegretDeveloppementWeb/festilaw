<?php

declare(strict_types=1);

namespace App\Enums\Notification;

enum FunnelNotificationReason: string
{
    case CreatorSubmission = 'creator_submission';
    case ProEnquiry = 'pro_enquiry';
    case ScaleAuditRequest = 'scale_audit_request';
    case PaymentReceived = 'payment_received';

    public function subject(): string
    {
        return match ($this) {
            self::CreatorSubmission => __('New Creator Pack submission'),
            self::ProEnquiry => __('New Pro Pack enquiry'),
            self::ScaleAuditRequest => __('New Scale Pack audit request'),
            self::PaymentReceived => __('Payment received'),
        };
    }
}
