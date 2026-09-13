<?php

namespace App\Models;

use App\Support\SpaceContentSchema;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['space_content_definition_id', 'version', 'schema', 'display', 'created_by_actor_id', 'content_hash', 'published_at'])]
class SpaceContentDefinitionVersion extends Model
{
    use HasFactory;

    private bool $publishing = false;

    private bool $discarding = false;

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'display' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $schema = SpaceContentSchema::normalizeDefinitionFields(($version->schema ?? [])['fields'] ?? []);
            $version->schema = $schema;
            $version->content_hash = SpaceContentSchema::hashArray($schema);
        });

        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') !== null) {
                throw new LogicException('Published Content Definition versions are immutable.');
            }

            if ($version->isDirty(['space_content_definition_id', 'version', 'created_by_actor_id'])) {
                throw new LogicException('Content Definition version provenance cannot be changed.');
            }

            if ($version->isDirty('published_at') && ! $version->publishing) {
                throw new LogicException('Content Definition versions must be activated through the lifecycle Action.');
            }

            if ($version->isDirty('schema')) {
                $schema = SpaceContentSchema::normalizeDefinitionFields(($version->schema ?? [])['fields'] ?? []);
                $version->schema = $schema;
                $version->content_hash = SpaceContentSchema::hashArray($schema);
            } elseif ($version->isDirty('content_hash')) {
                throw new LogicException('Content Definition version hashes are application-managed.');
            }
        });

        static::deleting(function (self $version): void {
            if (! $version->discarding
                || $version->published_at !== null
                || $version->revisions()->exists()) {
                throw new LogicException('Only unused unpublished Definition drafts may be discarded.');
            }
        });
    }

    public function publish(): void
    {
        if ($this->published_at !== null) {
            return;
        }

        $this->publishing = true;

        try {
            $this->update(['published_at' => now()]);
        } finally {
            $this->publishing = false;
        }
    }

    public function discard(): void
    {
        if ($this->published_at !== null || $this->revisions()->exists()) {
            throw new LogicException('Only unused unpublished Definition drafts may be discarded.');
        }

        $this->discarding = true;

        try {
            $this->delete();
        } finally {
            $this->discarding = false;
        }
    }

    /** @return BelongsTo<SpaceContentDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(SpaceContentDefinition::class, 'space_content_definition_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<SpaceContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(SpaceContentRevision::class, 'definition_version_id');
    }
}
