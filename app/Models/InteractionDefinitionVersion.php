<?php

namespace App\Models;

use App\Support\InteractionDefinitionConfig;
use Database\Factories\InteractionDefinitionVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'interaction_definition_id',
    'version',
    'purpose_key',
    'title',
    'instructions',
    'items',
    'settings',
    'evaluation_config',
    'space_content_revision_id',
    'created_by_actor_id',
    'content_hash',
    'published_at',
])]
class InteractionDefinitionVersion extends Model
{
    /** @use HasFactory<InteractionDefinitionVersionFactory> */
    use HasFactory;

    private bool $publishing = false;

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'settings' => 'array',
            'evaluation_config' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->normalizeConfiguration();
        });

        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') !== null) {
                throw new LogicException('Published Interaction Definition versions are immutable.');
            }

            if ($version->isDirty([
                'interaction_definition_id',
                'version',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Interaction Definition version provenance cannot change.');
            }

            if ($version->isDirty('published_at') && ! $version->publishing) {
                throw new LogicException('Interaction Definition versions must be activated through the lifecycle Action.');
            }

            $configuration = [
                'purpose_key',
                'title',
                'instructions',
                'items',
                'settings',
                'evaluation_config',
                'space_content_revision_id',
            ];

            if ($version->isDirty($configuration)) {
                $version->normalizeConfiguration();
            } elseif ($version->isDirty('content_hash')) {
                throw new LogicException('Interaction Definition version hashes are application-managed.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Interaction Definition versions are durable provenance.');
        });
    }

    public function publish(): void
    {
        if ($this->published_at !== null) {
            return;
        }

        $definition = InteractionDefinition::query()->findOrFail($this->interaction_definition_id);

        if ($definition->space_content_id !== null) {
            if ($this->space_content_revision_id === null) {
                throw new LogicException('Content-bound Interaction versions require an exact Content revision before activation.');
            }

            $revision = SpaceContentRevision::query()->findOrFail($this->space_content_revision_id);
            if ((int) $revision->space_content_id !== (int) $definition->space_content_id
                || ! $revision->hasVerifiableManifest()) {
                throw new LogicException('Content-bound Interaction versions require a sealed revision of the same Content.');
            }
        }

        $this->publishing = true;

        try {
            $this->update(['published_at' => now()]);
        } finally {
            $this->publishing = false;
        }
    }

    /** @return BelongsTo<InteractionDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(InteractionDefinition::class, 'interaction_definition_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function contentRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'space_content_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    private function normalizeConfiguration(): void
    {
        $definition = InteractionDefinition::query()->findOrFail($this->interaction_definition_id);
        $revisionUuid = null;

        if ($this->space_content_revision_id !== null) {
            $revision = SpaceContentRevision::query()->findOrFail($this->space_content_revision_id);

            if ($definition->space_content_id === null
                || (int) $revision->space_content_id !== (int) $definition->space_content_id) {
                throw new LogicException('Interaction Content revision must belong to the Definition Content.');
            }

            $revisionUuid = $revision->uuid;
        }

        $normalized = app(InteractionDefinitionConfig::class)->normalize(
            (string) ($this->purpose_key ?? 'general'),
            (string) ($this->title ?? ''),
            $this->instructions !== null ? (string) $this->instructions : null,
            is_array($this->items) ? $this->items : [],
            is_array($this->settings) ? $this->settings : [],
            is_array($this->evaluation_config) ? $this->evaluation_config : [],
            $revisionUuid,
        );

        foreach ($normalized as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }
}
