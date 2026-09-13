<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'parent_revision_id',
    'child_content_id',
    'relation_type',
    'position',
    'child_revision_id',
    'child_manifest_hash',
    'sealed_at',
])]
class SpaceContentRevisionRelationship extends Model
{
    use HasFactory;

    public const TYPE_CONTAINS = 'contains';

    /** @var list<string> */
    public const TYPES = [self::TYPE_CONTAINS];

    protected function casts(): array
    {
        return [
            'sealed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $relationship): void {
            if (! in_array($relationship->relation_type, self::TYPES, true)) {
                throw new LogicException('Unknown Content relationship type.');
            }

            if ((int) $relationship->position < 0) {
                throw new LogicException('Content relationship position cannot be negative.');
            }

            $parent = SpaceContentRevision::query()->with('content')->findOrFail($relationship->parent_revision_id);
            $child = SpaceContent::query()->findOrFail($relationship->child_content_id);

            if ((int) $parent->space_content_id === (int) $child->id) {
                throw new LogicException('Content cannot contain itself.');
            }

            if ((int) $parent->content->group_space_id !== (int) $child->group_space_id) {
                throw new LogicException('Content relationships must stay inside one Space.');
            }

            if ($relationship->child_revision_id !== null
                || $relationship->child_manifest_hash !== null
                || $relationship->sealed_at !== null) {
                throw new LogicException('Publication relationship evidence is sealed only during publishing.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Content revision relationships are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content revision relationships are immutable history.');
        });
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function parentRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'parent_revision_id');
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function childContent(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'child_content_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function childRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'child_revision_id');
    }
}
