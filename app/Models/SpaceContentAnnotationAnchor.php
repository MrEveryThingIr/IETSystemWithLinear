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
    'target_type',
    'target_uuid',
    'field_key',
    'selector',
    'position',
])]
class SpaceContentAnnotationAnchor extends Model
{
    public const TARGET_REVISION = 'revision';
    public const TARGET_FIELD = 'field';
    public const TARGET_TEXT = 'text';
    public const TARGET_ASSET = 'asset';
    public const TARGET_RELATIONSHIP = 'relationship';
    public const TARGET_BLOCK = 'block';

    /** @var list<string> */
    public const TARGET_TYPES = [
        self::TARGET_REVISION,
        self::TARGET_FIELD,
        self::TARGET_TEXT,
        self::TARGET_ASSET,
        self::TARGET_RELATIONSHIP,
        self::TARGET_BLOCK,
    ];

    protected function casts(): array
    {
        return [
            'selector' => 'array',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $anchor): void {
            $anchor->uuid ??= (string) Str::uuid();

            if (! in_array($anchor->target_type, self::TARGET_TYPES, true)) {
                throw new LogicException('Unknown Content annotation anchor target.');
            }

            if ((int) $anchor->position < 0) {
                throw new LogicException('Content annotation anchor position must be non-negative.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Content annotation anchors are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content annotation anchors are preserved with interaction history.');
        });
    }

    /** @return BelongsTo<SpaceContentAnnotation, $this> */
    public function annotation(): BelongsTo
    {
        return $this->belongsTo(SpaceContentAnnotation::class, 'annotation_id');
    }
}
