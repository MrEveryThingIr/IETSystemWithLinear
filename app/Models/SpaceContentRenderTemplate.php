<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'group_space_id',
    'creator_actor_id',
    'name',
    'base_key',
    'tokens',
    'status',
])]
class SpaceContentRenderTemplate extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return ['tokens' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->uuid ??= (string) Str::uuid();
            $template->name = trim($template->name);
            if ($template->name === '' || mb_strlen($template->name) > 120) {
                throw new LogicException('Content rendering template name is invalid.');
            }
            if (! in_array($template->status, [self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
                throw new LogicException('Unknown Content rendering template status.');
            }
        });

        static::updating(function (self $template): void {
            if ($template->isDirty(['uuid', 'group_space_id', 'creator_actor_id'])) {
                throw new LogicException('Rendering template identity is immutable.');
            }
            if ($template->isDirty('status') && ! in_array($template->status, [self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
                throw new LogicException('Unknown Content rendering template status.');
            }
        });
    }

    /** @return BelongsTo<GroupSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(GroupSpace::class, 'group_space_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'creator_actor_id');
    }

    /** @return BelongsToMany<Actor, $this> */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'space_content_render_template_favorites', 'render_template_id', 'actor_id')
            ->withTimestamps();
    }
}
