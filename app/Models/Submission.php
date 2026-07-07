<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Submission extends Model
{
    /** @use HasFactory<\Database\Factories\SubmissionFactory> */
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
}
