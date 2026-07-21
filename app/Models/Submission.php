<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Observers\SubmissionObserver;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([SubmissionObserver::class])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'type',
        'status',
        'locale',
        'company_name',
        'company_registration_number',
        'website_url',
        'first_name',
        'last_name',
        'email',
        'phone',
        'eu_sales_countries',
        'product_types',
        'message',
        'resume_token',
        'resume_expires_at',
        'meta',
        'eu_rp_address',
    ];

    protected function casts(): array
    {
        return [
            'type' => SubmissionType::class,
            'status' => SubmissionStatus::class,
            'eu_sales_countries' => 'array',
            'meta' => 'array',
            'resume_expires_at' => 'datetime',
        ];
    }

    /**
     * Le token de reprise (magic link) est la cle de route publique du dossier, pas l'id :
     * route('...', ['dossier' => $submission]) genere alors l'URL avec le resume_token, ce qui
     * correspond au binding {dossier} (cf. AppServiceProvider). Sans cela, la generation d'URL a
     * partir du modele (ex: le selecteur de langue sur le parcours) produirait l'id et renverrait 404.
     */
    public function getRouteKeyName(): string
    {
        return 'resume_token';
    }

    protected static function booted(): void
    {
        static::creating(function (Submission $submission): void {
            if (empty($submission->reference)) {
                $submission->reference = static::generateReference();
            }
        });
    }

    /**
     * A human-friendly, collision-checked reference, e.g. "FL-7K2Q-9RT4". Uppercase, no ambiguous
     * characters (no I/L/O/U/0/1), grouped for readability. ~30^8 combinations, plus a uniqueness check.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';

        do {
            $body = '';
            for ($i = 0; $i < 8; $i++) {
                $body .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'FL-'.substr($body, 0, 4).'-'.substr($body, 4, 4);
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Dossiers dont le lien de reprise (magic link) est encore valide : jamais expire,
     * ou expiration dans le futur. Filtre partage par le parcours STARTER et le back-office.
     *
     * @param  Builder<Submission>  $query
     */
    public function scopeResumable(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->whereNull('resume_expires_at')->orWhere('resume_expires_at', '>', now());
        });
    }

    public function quizResult(): HasOne
    {
        return $this->hasOne(QuizResult::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(UploadedDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Whether this is an active, paying customer's dossier — DERIVED from its payments, the single source
     * of truth: at least one succeeded, non-refunded subscription payment (year 1 or a renewal), and not
     * explicitly cancelled. A refund/chargeback (payment → Refunded) therefore deactivates the dossier on
     * its own. The stored `status` (Paid/Completed/Cancelled) stays a workflow/display cache, never the
     * authority on "is active". Uses the loaded payments relation when present to avoid an extra query.
     */
    public function isActive(): bool
    {
        if ($this->status === SubmissionStatus::Cancelled) {
            return false;
        }

        if ($this->relationLoaded('payments')) {
            return $this->payments->contains(
                fn (Payment $payment): bool => $payment->status === PaymentStatus::Succeeded && $payment->type->isSubscription(),
            );
        }

        return $this->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->whereIn('type', PaymentType::subscriptionCases())
            ->exists();
    }

    /**
     * Active dossiers (query scope mirroring isActive()): a succeeded, non-refunded subscription payment,
     * and not explicitly cancelled.
     *
     * @param  Builder<Submission>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', SubmissionStatus::Cancelled)
            ->whereHas('payments', function (Builder $inner): void {
                $inner->where('status', PaymentStatus::Succeeded)
                    ->whereIn('type', PaymentType::subscriptionCases());
            });
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    /** Notes internes de l'equipe (back-office), les plus recentes d'abord. */
    public function notes(): HasMany
    {
        return $this->hasMany(SubmissionNote::class)->latest();
    }
}
