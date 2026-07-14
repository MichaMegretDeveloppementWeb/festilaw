<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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

    protected static function booted(): void
    {
        static::creating(function (Submission $submission): void {
            if (empty($submission->reference)) {
                $submission->reference = (string) Str::uuid();
            }
        });
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

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }
}
