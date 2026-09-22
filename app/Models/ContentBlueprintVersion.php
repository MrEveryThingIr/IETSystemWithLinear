<?php

namespace App\Models;

use App\ContextKind;
use App\Support\ContentBlueprintConfig;
use Database\Factories\ContentBlueprintVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'content_blueprint_id',
    'version',
    'definition_schema',
    'initial_blocks',
    'render_template_key',
    'presentation',
    'context_kinds',
    'concept_defaults',
    'interaction_defaults',
    'authoring',
    'created_by_actor_id',
    'content_hash',
    'published_at',
])]
class ContentBlueprintVersion extends Model
{
    /** @use HasFactory<ContentBlueprintVersionFactory> */
    use HasFactory;

    private bool $publishing = false;

    protected function casts(): array
    {
        return [
            'definition_schema' => 'array',
            'initial_blocks' => 'array',
            'presentation' => 'array',
            'context_kinds' => 'array',
            'concept_defaults' => 'array',
            'interaction_defaults' => 'array',
            'authoring' => 'array',
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
                throw new LogicException('Published Content Blueprint versions are immutable.');
            }

            if ($version->isDirty([
                'content_blueprint_id',
                'version',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Content Blueprint version provenance cannot change.');
            }

            if ($version->isDirty('published_at') && ! $version->publishing) {
                throw new LogicException('Content Blueprint versions must be activated through the lifecycle Action.');
            }

            $configuration = [
                'definition_schema',
                'initial_blocks',
                'render_template_key',
                'presentation',
                'context_kinds',
                'concept_defaults',
                'interaction_defaults',
                'authoring',
            ];

            if ($version->isDirty($configuration)) {
                $version->normalizeConfiguration();
            } elseif ($version->isDirty('content_hash')) {
                throw new LogicException('Content Blueprint version hashes are application-managed.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Content Blueprint versions are durable provenance.');
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

    public function supportsContext(ContextKind $kind): bool
    {
        return in_array($kind->value, $this->context_kinds ?? [], true);
    }

    /** @return BelongsTo<ContentBlueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(ContentBlueprint::class, 'content_blueprint_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<SpaceContentDefinition, $this> */
    public function materializedDefinitions(): HasMany
    {
        return $this->hasMany(SpaceContentDefinition::class, 'content_blueprint_version_id');
    }

    /** @return HasMany<SpaceContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(SpaceContent::class, 'content_blueprint_version_id');
    }

    private function normalizeConfiguration(): void
    {
        $normalized = app(ContentBlueprintConfig::class)->normalize(
            $this->definition_schema ?? [],
            $this->initial_blocks ?? [],
            (string) ($this->render_template_key ?? 'article'),
            $this->presentation ?? [],
            $this->context_kinds ?? [],
            $this->concept_defaults ?? [],
            $this->interaction_defaults ?? [],
            $this->authoring ?? [],
        );

        foreach ($normalized as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }
}
