<?php

namespace App\Models;

use Database\Factories\ContentPlacementFactory;
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
    'placed_by_actor_id',
    'status',
    'removed_at',
    'removed_by_actor_id',
])]
class ContentPlacement extends Model
{
    /** @use HasFactory<ContentPlacementFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'removed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $placement): void {
            $placement->uuid ??= (string) Str::uuid();

            if (! in_array($placement->status, [self::STATUS_ACTIVE, self::STATUS_REMOVED], true)) {
                throw new LogicException('Unknown Content placement status.');
            }

            $content = SpaceContent::query()->findOrFail($placement->space_content_id);
            if ((int) $content->context_id === (int) $placement->context_id) {
                throw new LogicException('Content does not need a placement inside its home Context.');
            }
        });

        static::updating(function (self $placement): void {
            if ($placement->isDirty(['uuid', 'context_id', 'space_content_id', 'placed_by_actor_id'])) {
                throw new LogicException('Content placement identity and provenance cannot be reassigned.');
            }

            if ($placement->isDirty(['status', 'removed_at', 'removed_by_actor_id'])
                && ! $placement->applyingLifecycle) {
                throw new LogicException('Content placement lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Content placements are durable history; remove them through lifecycle state.');
        });
    }

    public function activate(): void
    {
        $this->applyLifecycle([
            'status' => self::STATUS_ACTIVE,
            'removed_at' => null,
            'removed_by_actor_id' => null,
        ]);
    }

    public function remove(Actor $actor): void
    {
        $this->applyLifecycle([
            'status' => self::STATUS_REMOVED,
            'removed_at' => now(),
            'removed_by_actor_id' => $actor->id,
        ]);
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

    /** @return BelongsTo<Actor, $this> */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'placed_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'removed_by_actor_id');
    }

    /** @param array<string, mixed> $attributes */
    private function applyLifecycle(array $attributes): void
    {
        $this->applyingLifecycle = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycle = false;
        }
    }
}
