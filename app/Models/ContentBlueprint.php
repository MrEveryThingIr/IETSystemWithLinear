<?php

namespace App\Models;

use Database\Factories\ContentBlueprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'slug',
    'name',
    'description',
    'category',
    'scope',
    'owner_actor_id',
    'context_id',
    'status',
    'current_version',
    'active_version_id',
    'draft_version_id',
    'cloned_from_version_id',
])]
class ContentBlueprint extends Model
{
    /** @use HasFactory<ContentBlueprintFactory> */
    use HasFactory;

    public const SCOPE_SYSTEM = 'system';

    public const SCOPE_ACTOR = 'actor';

    public const SCOPE_CONTEXT = 'context';

    /** @var list<string> */
    public const SCOPES = [
        self::SCOPE_SYSTEM,
        self::SCOPE_ACTOR,
        self::SCOPE_CONTEXT,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'category' => 'general',
        'scope' => self::SCOPE_SYSTEM,
        'status' => self::STATUS_ACTIVE,
        'current_version' => 1,
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $blueprint): void {
            $blueprint->uuid ??= (string) Str::uuid();
            $blueprint->normalizeIdentity();
        });

        static::updating(function (self $blueprint): void {
            $blueprint->normalizeIdentity();

            if ($blueprint->isDirty([
                'uuid',
                'slug',
                'scope',
                'owner_actor_id',
                'context_id',
                'cloned_from_version_id',
            ])) {
                throw new LogicException('Content Blueprint identity and provenance cannot be reassigned.');
            }

            if ($blueprint->isDirty([
                'status',
                'current_version',
                'active_version_id',
                'draft_version_id',
            ]) && ! $blueprint->applyingLifecycle) {
                throw new LogicException('Content Blueprint lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Content Blueprints are durable identity; archive them instead.');
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
            throw new LogicException('Only Content Blueprint lifecycle attributes may change through this operation.');
        }

        foreach (['active_version_id', 'draft_version_id'] as $attribute) {
            $versionId = $attributes[$attribute] ?? null;
            if ($versionId === null) {
                continue;
            }

            $belongs = $this->versions()->whereKey($versionId)->exists();
            if (! $belongs) {
                throw new LogicException('Content Blueprint version pointers must belong to the same Blueprint.');
            }
        }

        $this->applyingLifecycle = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return HasMany<ContentBlueprintVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ContentBlueprintVersion::class);
    }

    /** @return BelongsTo<ContentBlueprintVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(ContentBlueprintVersion::class, 'active_version_id');
    }

    /** @return BelongsTo<ContentBlueprintVersion, $this> */
    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(ContentBlueprintVersion::class, 'draft_version_id');
    }

    /** @return BelongsTo<ContentBlueprintVersion, $this> */
    public function clonedFromVersion(): BelongsTo
    {
        return $this->belongsTo(ContentBlueprintVersion::class, 'cloned_from_version_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'owner_actor_id');
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    public function activeVersionRecord(): ?ContentBlueprintVersion
    {
        if ($this->active_version_id !== null) {
            return $this->activeVersion()->first();
        }

        return $this->versions()
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    private function normalizeIdentity(): void
    {
        $this->slug = strtolower(trim((string) $this->slug));
        $this->name = trim((string) $this->name);
        $this->description = $this->description !== null ? trim((string) $this->description) : null;
        $this->description = $this->description === '' ? null : $this->description;
        $this->category = strtolower(trim((string) $this->category));

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slug) || mb_strlen($this->slug) > 120) {
            throw new LogicException('Content Blueprint slug is invalid.');
        }
        if ($this->name === '' || mb_strlen($this->name) > 120) {
            throw new LogicException('Content Blueprint name is invalid.');
        }
        if ($this->description !== null && mb_strlen($this->description) > 2000) {
            throw new LogicException('Content Blueprint description is too long.');
        }
        if ($this->category === '' || mb_strlen($this->category) > 64) {
            throw new LogicException('Content Blueprint category is invalid.');
        }
        if (! in_array($this->scope, self::SCOPES, true)) {
            throw new LogicException('Unknown Content Blueprint scope.');
        }
        if (! in_array($this->status, self::STATUSES, true)) {
            throw new LogicException('Unknown Content Blueprint status.');
        }
        if ((int) $this->current_version < 1) {
            throw new LogicException('Content Blueprint version must be positive.');
        }

        if ($this->scope === self::SCOPE_SYSTEM
            && ($this->owner_actor_id !== null || $this->context_id !== null)) {
            throw new LogicException('System Content Blueprints cannot have Actor or Context ownership.');
        }

        if ($this->scope === self::SCOPE_ACTOR
            && ($this->owner_actor_id === null || $this->context_id !== null)) {
            throw new LogicException('Actor Content Blueprints require exactly one Actor owner.');
        }

        if ($this->scope === self::SCOPE_CONTEXT && $this->context_id === null) {
            throw new LogicException('Context Content Blueprints require a Context owner.');
        }
    }
}
