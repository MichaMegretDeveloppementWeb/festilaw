<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Document\DocumentType;
use Database\Factories\UploadedDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedDocument extends Model
{
    /** @use HasFactory<UploadedDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'type',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
