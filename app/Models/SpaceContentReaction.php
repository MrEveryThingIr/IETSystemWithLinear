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
    'space_content_revision_id',
    'actor_id',
    'type',
])]
class SpaceContentReaction extends Model
{
    public const TYPE_LIKE = 'like';
    public const TYPE_HELPFUL = 'helpful';
    public const TYPE_INSIGHTFUL = 'insightful';

    /** @var list<string> */
    public const TYPES = [self::TYPE_LIKE, self::TYPE_HELPFUL, self::TYPE_INSIGHTFUL];

    protected static function booted(): void
    {
        static::creating(function (self $reaction): void {
            $reaction->uuid ??= (string) Str::uuid();

            if (! in_array($reaction->type, self::TYPES, true)) {
                throw new LogicException('Unknown Content reaction type.');
            }

            $revision = SpaceContentRevision::query()->findOrFail($reaction->space_content_revision_id);
            if ((int) $revision->space_content_id !== (int) $reaction->space_content_id) {
                throw new LogicException('Content reaction revision must belong to the same Content.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Content reactions are immutable; toggle them instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'space_content_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }
}
