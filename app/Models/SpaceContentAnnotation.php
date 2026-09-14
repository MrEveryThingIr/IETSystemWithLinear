<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'space_content_id',
    'space_content_revision_id',
    'parent_annotation_id',
    'author_actor_id',
    'kind',
    'visibility',
    'status',
    'body',
])]
class SpaceContentAnnotation extends Model
{
    public const KIND_COMMENT = 'comment';

    public const VISIBILITY_SPACE = 'space';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @var list<string> */
    public const KINDS = [self::KIND_COMMENT];

    /** @var list<string> */
    public const VISIBILITIES = [self::VISIBILITY_SPACE];

    /** @var list<string> */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_WITHDRAWN];

    protected static function booted(): void
    {
        static::creating(function (self $annotation): void {
            $annotation->uuid ??= (string) Str::uuid();
            $annotation->body = trim($annotation->body);

            if (! in_array($annotation->kind, self::KINDS, true)) {
                throw new LogicException('Unknown Content annotation kind.');
            }

            if (! in_array($annotation->visibility, self::VISIBILITIES, true)) {
                throw new LogicException('Unknown Content annotation visibility.');
            }

            if (! in_array($annotation->status, self::STATUSES, true)) {
                throw new LogicException('Unknown Content annotation status.');
            }

            if ($annotation->body === '' || mb_strlen($annotation->body) > 5000) {
                throw new LogicException('Content annotation body must contain between 1 and 5000 characters.');
            }

            $revision = SpaceContentRevision::query()->findOrFail($annotation->space_content_revision_id);
            if ((int) $revision->space_content_id !== (int) $annotation->space_content_id) {
                throw new LogicException('Content annotation revision must belong to the same Content.');
            }

            if ($annotation->parent_annotation_id === null) {
                return;
            }

            $parent = self::query()->findOrFail($annotation->parent_annotation_id);
            if (
                (int) $parent->space_content_id !== (int) $annotation->space_content_id
                || (int) $parent->space_content_revision_id !== (int) $annotation->space_content_revision_id
                || $parent->parent_annotation_id !== null
                || $parent->kind !== self::KIND_COMMENT
                || $parent->status !== self::STATUS_ACTIVE
            ) {
                throw new LogicException('Content replies must target an active top-level comment on the same edition.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Content annotations are immutable in this interaction version.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content annotations are preserved as interaction history.');
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

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_annotation_id');
    }

    /** @return HasMany<self, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_annotation_id')
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'author_actor_id');
    }
}
