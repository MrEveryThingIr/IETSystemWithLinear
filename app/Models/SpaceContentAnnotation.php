<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public const KIND_NOTE = 'note';

    public const KIND_QUESTION = 'question';

    public const KIND_ANSWER = 'answer';

    public const KIND_REPLY = 'reply';

    public const KIND_CORRECTION = 'correction';

    public const KIND_IDEA = 'idea';

    public const VISIBILITY_SPACE = 'space';

    public const VISIBILITY_PRIVATE = 'private';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @var list<string> */
    public const TOP_LEVEL_KINDS = [
        self::KIND_COMMENT,
        self::KIND_NOTE,
        self::KIND_QUESTION,
        self::KIND_CORRECTION,
        self::KIND_IDEA,
    ];

    /** @var list<string> */
    public const CHILD_KINDS = [self::KIND_REPLY, self::KIND_ANSWER];

    /** @var list<string> */
    public const KINDS = [
        ...self::TOP_LEVEL_KINDS,
        ...self::CHILD_KINDS,
    ];

    /** @var list<string> */
    public const VISIBILITIES = [self::VISIBILITY_SPACE, self::VISIBILITY_PRIVATE];

    /** @var list<string> */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_WITHDRAWN];

    protected static function booted(): void
    {
        static::creating(function (self $annotation): void {
            $annotation->uuid ??= (string) Str::uuid();
            $annotation->body = trim((string) ($annotation->body ?? ''));

            if (!in_array($annotation->kind, self::KINDS, true)) {
                throw new LogicException('Unknown Content annotation kind.');
            }

            if (!in_array($annotation->visibility, self::VISIBILITIES, true)) {
                throw new LogicException('Unknown Content annotation visibility.');
            }

            if (!in_array($annotation->status, self::STATUSES, true)) {
                throw new LogicException('Unknown Content annotation status.');
            }

            if (mb_strlen($annotation->body) > 5000) {
                throw new LogicException('Content annotation body may not exceed 5000 characters.');
            }

            $revision = SpaceContentRevision::query()->findOrFail($annotation->space_content_revision_id);
            if ((int) $revision->space_content_id !== (int) $annotation->space_content_id) {
                throw new LogicException('Content annotation revision must belong to the same Content.');
            }

            if ($annotation->parent_annotation_id === null) {
                if (!in_array($annotation->kind, self::TOP_LEVEL_KINDS, true)) {
                    throw new LogicException('This annotation role must be a reply to another annotation.');
                }

                return;
            }

            $parent = self::query()->findOrFail($annotation->parent_annotation_id);
            if (
                (int) $parent->space_content_id !== (int) $annotation->space_content_id
                || (int) $parent->space_content_revision_id !== (int) $annotation->space_content_revision_id
                || $parent->parent_annotation_id !== null
                || $parent->status !== self::STATUS_ACTIVE
                || $parent->visibility !== $annotation->visibility
            ) {
                throw new LogicException('Content replies must target an active top-level annotation on the same edition and visibility scope.');
            }

            if ($annotation->kind === self::KIND_ANSWER && $parent->kind !== self::KIND_QUESTION) {
                throw new LogicException('Answers must target questions.');
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

    /** @return HasMany<SpaceContentAnnotationAnchor, $this> */
    public function anchors(): HasMany
    {
        return $this->hasMany(SpaceContentAnnotationAnchor::class, 'annotation_id')->orderBy('position');
    }

    /** @return HasMany<SpaceContentAnnotationDisposition, $this> */
    public function dispositions(): HasMany
    {
        return $this->hasMany(SpaceContentAnnotationDisposition::class, 'annotation_id')
            ->orderBy('id');
    }

    /** @return HasOne<SpaceContentAnnotationDisposition, $this> */
    public function latestDisposition(): HasOne
    {
        return $this->hasOne(SpaceContentAnnotationDisposition::class, 'annotation_id')
            ->ofMany('id', 'max');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'space_content_annotation_assets', 'annotation_id', 'asset_id')
            ->withPivot(['uuid', 'role', 'position', 'caption'])
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
