<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use Database\Factories\BlogGenerationStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une etape d'une generation : son resultat (`output`), sa consommation et son cout, enregistres des qu'elle
 * se termine. Jetons, recherches web et cout se cumulent sur tous les essais de l'etape.
 */
class BlogGenerationStep extends Model
{
    /** @use HasFactory<BlogGenerationStepFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_generation_id',
        'step',
        'position',
        'status',
        'attempt',
        'model',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'reasoning_tokens',
        'web_searches',
        'cost_microusd',
        'cost_is_estimated',
        'duration_ms',
        'error',
        'summary',
        'output',
        'started_at',
        'finished_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'attempt' => 0,
        'input_tokens' => 0,
        'output_tokens' => 0,
        'cached_tokens' => 0,
        'reasoning_tokens' => 0,
        'web_searches' => 0,
        'cost_microusd' => 0,
        'cost_is_estimated' => false,
        'duration_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => BlogGenerationStepStatus::class,
            'position' => 'integer',
            'attempt' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'web_searches' => 'integer',
            'cost_microusd' => 'integer',
            'cost_is_estimated' => 'boolean',
            'duration_ms' => 'integer',
            'output' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BlogGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(BlogGeneration::class, 'blog_generation_id');
    }
}
