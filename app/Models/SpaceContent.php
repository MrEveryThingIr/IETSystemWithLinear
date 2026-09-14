<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'group_space_id',
    'space_content_definition_id',
    'author_actor_id',
    'status',
    'current_revision',
    'active_revision_id',
    'draft_revision_id',
    'published_at',
    'archived_at',
])]
class SpaceContent extends Model
{
    use HasFactory;

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => 'draft',
        'current_revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $content): void {
            $content->uuid ??= (string) Str::uuid();
            $definition = SpaceContentDefinition::query()->findOrFail($content->space_content_definition_id);

            if ((int) $definition->group_space_id !== (int) $content->group_space_id) {
                throw new LogicException('Content Definition must belong to the same Space as Content.');
            }

            if (! in_array($content->status, ['draft', 'published', 'archived'], true)) {
                throw new LogicException('Unknown Content status.');
            }

            if ((int) $content->current_revision < 1) {
                throw new LogicException('Content current revision must be positive.');
            }
        });

        static::updating(function (self $content): void {
            if ($content->isDirty(['uuid', 'group_space_id', 'space_content_definition_id', 'author_actor_id'])) {
                throw new LogicException('Content identity and provenance cannot be reassigned.');
            }

            foreach (['active_revision_id', 'draft_revision_id'] as $attribute) {
                if (! $content->isDirty($attribute)) {
                    continue;
                }

                $revisionId = $content->getAttribute($attribute);

                if ($revisionId === null) {
                    continue;
                }

                $belongs = SpaceContentRevision::query()
                    ->whereKey($revisionId)
                    ->where('space_content_id', $content->id)
                    ->exists();

                if (! $belongs) {
                    throw new LogicException('Content revision pointers must belong to the same Content.');
                }
            }

            if ($content->isDirty([
                'status',
                'current_revision',
                'active_revision_id',
                'draft_revision_id',
                'published_at',
                'archived_at',
            ]) && ! $content->applyingLifecycle) {
                throw new LogicException('Content lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Content is preserved; archive it instead.');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function applyLifecycle(array $attributes): void
    {
        $allowed = [
            'status',
            'current_revision',
            'active_revision_id',
            'draft_revision_id',
            'published_at',
            'archived_at',
        ];

        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new LogicException('Only Content lifecycle attributes may be changed through this operation.');
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

    /** @return BelongsTo<SpaceContentDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(SpaceContentDefinition::class, 'space_content_definition_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'author_actor_id');
    }

    /** @return HasMany<SpaceContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(SpaceContentRevision::class, 'space_content_id');
    }

    /** @return HasMany<SpaceContentLifecycleEvent, $this> */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(SpaceContentLifecycleEvent::class, 'space_content_id')->orderByDesc('created_at');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function activeRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'active_revision_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function draftRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'draft_revision_id');
    }

    /** @return HasOne<SpaceContentRevision, $this> */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(SpaceContentRevision::class, 'space_content_id')->ofMany('revision', 'max');
    }

    public function activeRevisionRecord(): ?SpaceContentRevision
    {
        if ($this->active_revision_id !== null) {
            return $this->activeRevision()->first();
        }

        if ($this->published_at === null) {
            return null;
        }

        return $this->revisions()
            ->where('revision', $this->current_revision)
            ->first();
    }

    public function draftRevisionRecord(): ?SpaceContentRevision
    {
        if ($this->draft_revision_id !== null) {
            return $this->draftRevision()->first();
        }

        if ($this->published_at !== null) {
            return null;
        }

        return $this->revisions()
            ->where('revision', $this->current_revision)
            ->first();
    }

    public function currentRevisionRecord(): SpaceContentRevision
    {
        return $this->draftRevisionRecord()
            ?? $this->activeRevisionRecord()
            ?? $this->revisions()->where('revision', $this->current_revision)->firstOrFail();
    }
}
