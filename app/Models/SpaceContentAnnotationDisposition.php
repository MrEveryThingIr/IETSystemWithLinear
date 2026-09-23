<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'annotation_id',
    'status',
    'incorporated_revision_id',
    'note',
    'resolved_by_actor_id',
])]
class SpaceContentAnnotationDisposition extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_INCORPORATED = 'incorporated';

    public const STATUS_SUPERSEDED = 'superseded';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_REVIEWED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_INCORPORATED,
        self::STATUS_SUPERSEDED,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $disposition): void {
            $disposition->uuid ??= (string) Str::uuid();
            $disposition->note = $disposition->note !== null
                ? trim((string) $disposition->note)
                : null;
            $disposition->note = $disposition->note === '' ? null : $disposition->note;

            if (!in_array($disposition->status, self::STATUSES, true)) {
                throw new LogicException('Unknown Content annotation disposition.');
            }

            if ($disposition->note !== null && mb_strlen($disposition->note) > 2000) {
                throw new LogicException('Content annotation disposition note may not exceed 2000 characters.');
            }

            $annotation = SpaceContentAnnotation::query()
                ->with('revision')
                ->findOrFail($disposition->annotation_id);

            if ($disposition->status !== self::STATUS_INCORPORATED) {
                if ($disposition->incorporated_revision_id !== null) {
                    throw new LogicException('Only incorporated feedback may reference an incorporated revision.');
                }

                return;
            }

            if ($disposition->incorporated_revision_id === null) {
                throw new LogicException('Incorporated feedback requires an official Content revision.');
            }

            $revision = SpaceContentRevision::query()->findOrFail($disposition->incorporated_revision_id);

            if ((int) $revision->space_content_id !== (int) $annotation->space_content_id
                || $revision->revision <= $annotation->revision->revision
                || ! $revision->hasVerifiableManifest()) {
                throw new LogicException('Incorporated feedback must reference a later sealed revision of the same Content.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Content annotation dispositions are immutable history.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content annotation dispositions are immutable history.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<SpaceContentAnnotation, $this> */
    public function annotation(): BelongsTo
    {
        return $this->belongsTo(SpaceContentAnnotation::class, 'annotation_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function incorporatedRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'incorporated_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'resolved_by_actor_id');
    }
}
