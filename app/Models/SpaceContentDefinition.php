<?php

namespace App\Models;

use App\Support\ContextScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'context_id',
    'content_blueprint_version_id',
    'group_space_id',
    'created_by_actor_id',
    'name',
    'slug',
    'description',
    'status',
    'current_version',
    'active_version_id',
    'draft_version_id',
])]
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
            $context = Context::query()->findOrFail($definition->context_id);
            ContextScope::assertLegacyGroupSpace(
                $context,
                $definition->group_space_id !== null ? (int) $definition->group_space_id : null,
                'Content Definition',
            );

            if (! in_array($definition->status, ['draft', 'active', 'archived'], true)) {
                throw new LogicException('Unknown Content Definition status.');
            }

            if ((int) $definition->current_version < 1) {
                throw new LogicException('Content Definition current version must be positive.');
            }

            foreach (['active_version_id', 'draft_version_id'] as $attribute) {
                $versionId = $definition->getAttribute($attribute);

                if ($versionId === null || ! $definition->exists) {
                    continue;
                }

                $belongs = SpaceContentDefinitionVersion::query()
                    ->whereKey($versionId)
                    ->where('space_content_definition_id', $definition->id)
                    ->exists();

                if (! $belongs) {
                    throw new LogicException('Content Definition version pointers must belong to the same Definition.');
                }
            }
        });

        static::updating(function (self $definition): void {
            if ($definition->isDirty(['context_id', 'content_blueprint_version_id', 'group_space_id', 'created_by_actor_id', 'slug'])) {
                throw new LogicException('Content Definition provenance and stable slug cannot be reassigned.');
            }

            if ($definition->isDirty([
                'status',
                'current_version',
                'active_version_id',
                'draft_version_id',
            ]) && ! $definition->applyingLifecycle) {
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
        $allowed = [
            'status',
            'current_version',
            'active_version_id',
            'draft_version_id',
        ];

        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new LogicException('Only Content Definition lifecycle attributes may change through this operation.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<ContentBlueprintVersion, $this> */
    public function blueprintVersion(): BelongsTo
    {
        return $this->belongsTo(ContentBlueprintVersion::class, 'content_blueprint_version_id');
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

    /** @return BelongsTo<SpaceContentDefinitionVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(SpaceContentDefinitionVersion::class, 'active_version_id');
    }

    /** @return BelongsTo<SpaceContentDefinitionVersion, $this> */
    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(SpaceContentDefinitionVersion::class, 'draft_version_id');
    }

    /** @return HasMany<SpaceContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(SpaceContent::class, 'space_content_definition_id');
    }

    public function activeVersionRecord(): ?SpaceContentDefinitionVersion
    {
        if ($this->active_version_id !== null) {
            return $this->activeVersion()->first();
        }

        return $this->versions()
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    public function draftVersionRecord(): ?SpaceContentDefinitionVersion
    {
        if ($this->draft_version_id !== null) {
            return $this->draftVersion()->first();
        }

        return $this->versions()
            ->where('version', $this->current_version)
            ->whereNull('published_at')
            ->first();
    }

    public function currentVersionRecord(): SpaceContentDefinitionVersion
    {
        return $this->draftVersionRecord()
            ?? $this->activeVersionRecord()
            ?? $this->versions()->where('version', $this->current_version)->firstOrFail();
    }
}
