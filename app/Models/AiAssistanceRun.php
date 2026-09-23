<?php

namespace App\Models;

use Database\Factories\AiAssistanceRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'context_id',
    'space_content_id',
    'base_revision_id',
    'requested_by_actor_id',
    'status',
    'provider',
    'model',
    'external_response_id',
    'prompt',
    'request_hash',
    'proposal',
    'error',
    'planned_at',
    'applied_revision_id',
    'applied_at',
])]
class AiAssistanceRun extends Model
{
    /** @use HasFactory<AiAssistanceRunFactory> */
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->uuid ??= (string) Str::uuid();

            if (! in_array($run->status, [self::STATUS_PLANNED, self::STATUS_APPLIED, self::STATUS_FAILED], true)) {
                throw new LogicException('Unknown AI assistance status.');
            }
        });

        static::updating(function (self $run): void {
            if ($run->isDirty([
                'uuid',
                'context_id',
                'space_content_id',
                'base_revision_id',
                'requested_by_actor_id',
                'provider',
                'model',
                'external_response_id',
                'prompt',
                'request_hash',
                'proposal',
                'planned_at',
            ])) {
                throw new LogicException('AI assistance provenance is immutable after planning.');
            }

            if (! in_array($run->status, [self::STATUS_PLANNED, self::STATUS_APPLIED, self::STATUS_FAILED], true)) {
                throw new LogicException('Unknown AI assistance status.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('AI assistance runs are preserved as provenance.');
        });
    }

    protected function casts(): array
    {
        return [
            'proposal' => 'array',
            'planned_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function baseRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'base_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'requested_by_actor_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function appliedRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'applied_revision_id');
    }
}
