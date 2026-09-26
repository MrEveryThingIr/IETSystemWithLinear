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
    'logical_uuid',
    'space_content_revision_id',
    'parent_block_id',
    'type',
    'position',
    'data',
    'style',
])]
class SpaceContentBlock extends Model
{
    public const TYPE_PARAGRAPH = 'paragraph';

    public const TYPE_HEADING = 'heading';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_LIST = 'list';

    public const TYPE_CALLOUT = 'callout';

    public const TYPE_DIVIDER = 'divider';

    public const TYPE_FIELD = 'field';

    public const TYPE_IMAGE = 'image';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_VIDEO = 'video';

    public const TYPE_FILE = 'file';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_PARAGRAPH,
        self::TYPE_HEADING,
        self::TYPE_QUOTE,
        self::TYPE_LIST,
        self::TYPE_CALLOUT,
        self::TYPE_DIVIDER,
        self::TYPE_FIELD,
        self::TYPE_IMAGE,
        self::TYPE_AUDIO,
        self::TYPE_VIDEO,
        self::TYPE_FILE,
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'style' => 'array',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $block): void {
            $block->uuid ??= (string) Str::uuid();
            $block->logical_uuid ??= (string) Str::uuid();

            if (! in_array($block->type, self::TYPES, true)) {
                throw new LogicException('Unknown Content block type.');
            }

            if ((int) $block->position < 0) {
                throw new LogicException('Content block position must be non-negative.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Content blocks are immutable revision evidence.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content blocks are preserved with revision history.');
        });
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'space_content_revision_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_block_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_block_id')->orderBy('position');
    }
}
