<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'space_content_id',
    'actor_id',
    'event_type',
    'from_status',
    'to_status',
    'reason',
    'metadata',
    'created_at',
])]
class SpaceContentLifecycleEvent extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->created_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Content lifecycle events are immutable evidence.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content lifecycle events are immutable evidence.');
        });
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }
}
