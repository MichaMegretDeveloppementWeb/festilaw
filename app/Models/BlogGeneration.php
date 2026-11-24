<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Enums\Blog\Generation\BlogTopicKind;
use Database\Factories\BlogGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une generation d'article par IA (ou une traduction assistee) : la demande, son avancement et son issue.
 * Elle est traitee etape par etape par le worker ; chaque etape terminee est une ligne BlogGenerationStep.
 * Les couts sont en millioniemes de dollar (`cost_microusd`).
 */
class BlogGeneration extends Model
{
    /** @use HasFactory<BlogGenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'trigger',
        'status',
        'outcome_reason',
        'current_step',
        'requested_by',
        'batch_uuid',
        'batch_notified_at',
        'topic_hint',
        'instructions',
        'topic_title',
        'topic_summary',
        'topic_kind',
        'topic_rejected_at',
        'blog_post_id',
        'source_blog_post_id',
        'payload',
        'cost_microusd',
        'forecast_low_microusd',
        'forecast_high_microusd',
        'started_at',
        'finished_at',
        'delivered_at',
        'locked_until',
        'not_before',
        'lock_owner',
        'cancel_requested_at',
        'cancel_requested_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'type' => 'article',
        'status' => 'queued',
        'cost_microusd' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => BlogGenerationType::class,
            'trigger' => BlogGenerationTrigger::class,
            'status' => BlogGenerationStatus::class,
            'outcome_reason' => BlogGenerationOutcomeReason::class,
            'topic_kind' => BlogTopicKind::class,
            'batch_notified_at' => 'immutable_datetime',
            'topic_rejected_at' => 'immutable_datetime',
            'payload' => 'array',
            'cost_microusd' => 'integer',
            'forecast_low_microusd' => 'integer',
            'forecast_high_microusd' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
            'not_before' => 'immutable_datetime',
            'cancel_requested_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<BlogGenerationStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(BlogGenerationStep::class)->orderBy('position');
    }

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
