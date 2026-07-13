<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Quiz\QuizOutcome;
use Database\Factories\QuizResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizResult extends Model
{
    /** @use HasFactory<QuizResultFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'q1_based_outside_eu',
        'q2_eu_countries',
        'q3_sells_restricted',
        'outcome',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'q1_based_outside_eu' => 'boolean',
            'q2_eu_countries' => 'array',
            'q3_sells_restricted' => 'boolean',
            'outcome' => QuizOutcome::class,
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
