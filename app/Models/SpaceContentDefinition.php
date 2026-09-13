<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['group_space_id', 'created_by_actor_id', 'name', 'slug', 'description', 'status', 'current_version'])]
class SpaceContentDefinition extends Model
{
    use HasFactory;

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => 'draft',
        'current_version' => 1,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $definition): void {
            if (! in_array($definition->status, ['draft', 'active', 'archived'], true)) {
                throw new LogicException('Unknown Content Definition status.');
            }
            if ((int) $definition->current_version < 1) {
                throw new LogicException('Content Definition current version must be positive.');
            }
        });

        static::updating(function (self $definition): void {
            if ($definition->isDirty(['group_space_id', 'created_by_actor_id', 'slug'])) {
                throw new LogicException('Content Definition provenance and stable slug cannot be reassigned.');
            }

            if ($definition->isDirty(['status', 'current_version']) && ! $definition->applyingLifecycle) {
                throw new LogicException('Content Definition lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Content Definitions are preserved; archive them instead.');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function applyLifecycle(array $attributes): void
    {
        if (array_diff(array_keys($attributes), ['status', 'current_version']) !== []) {
            throw new LogicException('Only Content Definition lifecycle attributes may change through this operation.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return BelongsTo<GroupSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(GroupSpace::class, 'group_space_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<SpaceContentDefinitionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(SpaceContentDefinitionVersion::class);
    }

    /** @return HasMany<SpaceContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(SpaceContent::class, 'space_content_definition_id');
    }

    public function currentVersionRecord(): SpaceContentDefinitionVersion
    {
        return $this->versions()->where('version', $this->current_version)->firstOrFail();
    }
}
