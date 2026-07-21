<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'type',
        'amount_cents',
        'service_year',
        'currency',
        'provider',
        'provider_reference',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'amount_cents' => 'integer',
            'service_year' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** Display name of the payment provider (for user-facing messages), e.g. "Stripe". */
    public function providerLabel(): string
    {
        return match ((string) $this->provider) {
            'stripe' => 'Stripe',
            'fake' => 'Fake',
            default => ucfirst((string) $this->provider),
        };
    }
}
