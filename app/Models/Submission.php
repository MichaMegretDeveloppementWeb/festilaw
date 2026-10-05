<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\ContractRole;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'replaced_by_id',
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
     * Rotates the resume token to a fresh unguessable value. Called on every magic-link request (client
     * find-my-file or admin resend) so the emailed link is always the only valid one · a previously sent
     * link can no longer be reused, which removes the whole class of stale/leaked-link problems.
     */
    public function regenerateResumeToken(): void
    {
        $this->update(['resume_token' => Str::random(48)]);
    }

    /**
     * Ferme l'acces client : le lien magique, ceux des anciens e-mails et les telechargements menent a la
     * page "ce lien n'est plus valide". Fin de la relation (dossier annule), cf. politique de confidentialite.
     */
    public function closeAccess(): void
    {
        if ((string) $this->resume_token === '') {
            return;
        }

        $this->update(['resume_expires_at' => now()]);
    }

    /**
     * Rend le lien magique valable selon l'etat du dossier : sans expiration des qu'un paiement a abouti
     * (comme a la confirmation du paiement), sinon pour la duree du lien de son pack. A chaque envoi du lien
     * (l'e-mail en annonce la duree) et a la reouverture d'un dossier annule.
     */
    public function refreshAccess(): void
    {
        if ((string) $this->resume_token === '') {
            return;
        }

        $paid = $this->payments()->where('status', PaymentStatus::Succeeded)->exists();

        $this->update(['resume_expires_at' => $paid ? null : now()->addDays($this->resumeTtlDays())]);
    }

    /** Duree de validite (jours) du lien magique d'un dossier non paye, selon son pack. */
    private function resumeTtlDays(): int
    {
        return (int) ($this->type === SubmissionType::Scale
            ? config('festilaw.scale.resume_ttl_days', 30)
            : config('festilaw.starter.resume_ttl_days', 30));
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

    /**
     * Le mandat EN VIGUEUR du dossier (un seul, tenu par le code). Un changement de pack apres paiement (SC12)
     * ajoute un mandat en attente puis remplace celui-ci ; l'historique complet est dans contracts().
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('role', ContractRole::Current->value);
    }

    /** Tous les mandats du dossier (en vigueur, en attente, remplaces), du plus ancien au plus recent. */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderBy('id');
    }

    /** Changements de pack apres paiement (SC12), les plus recents d'abord. */
    public function packChanges(): HasMany
    {
        return $this->hasMany(PackChange::class)->latest('id');
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
     * Whether this is an active, paying customer's dossier · DERIVED from its payments, the single source
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

    /** Dossier ouvert a la place de celui-ci lors d'un changement de pack avant paiement. */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    /** Dossier que celui-ci remplace (changement de pack avant paiement), s'il y en a un. */
    public function replaces(): HasOne
    {
        return $this->hasOne(self::class, 'replaced_by_id');
    }

    /** Notes internes de l'equipe (back-office), les plus recentes d'abord. */
    public function notes(): HasMany
    {
        return $this->hasMany(SubmissionNote::class)->latest();
    }
}
