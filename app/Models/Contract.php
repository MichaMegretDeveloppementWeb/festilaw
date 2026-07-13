<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\SignatureStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'filled_fields',
        'signature_status',
        'signature_provider',
        'signature_provider_reference',
        'signed_file_path',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'filled_fields' => 'array',
            'signature_status' => SignatureStatus::class,
            'signed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
