<?php

namespace App\Models;

use Database\Factories\InteractionDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'context_id',
    'space_content_id',
    'name',
    'status',
    'current_version',
    'active_version_id',
    'draft_version_id',
    'created_by_actor_id',
])]
class InteractionDefinition extends Model
{
    /** @use HasFactory<InteractionDefinitionFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_RETIRED,
    ];

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'current_version' => 1,
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            $definition->uuid ??= (string) Str::uuid();
            $definition->normalizeIdentity();
        });

        static::updating(function (self $definition): void {
            $definition->normalizeIdentity();

            if ($definition->isDirty([
                'uuid',
                'context_id',
                'space_content_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Interaction Definition identity and provenance cannot be reassigned.');
            }

            if ($definition->isDirty([
                'status',
                'current_version',
                'active_version_id',
                'draft_version_id',
            ]) && ! $definition->applyingLifecycle) {
                throw new LogicException('Interaction Definition lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Interaction Definitions are durable identity; retire them instead.');
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
            throw new LogicException('Only Interaction Definition lifecycle attributes may change through this operation.');
        }

        foreach (['active_version_id', 'draft_version_id'] as $attribute) {
            $versionId = $attributes[$attribute] ?? null;
            if ($versionId === null) {
                continue;
            }

            if (! $this->versions()->whereKey($versionId)->exists()) {
                throw new LogicException('Interaction Definition version pointers must belong to the same Definition.');
            }
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

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<InteractionDefinitionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(InteractionDefinitionVersion::class);
    }

    /** @return BelongsTo<InteractionDefinitionVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(InteractionDefinitionVersion::class, 'active_version_id');
    }

    /** @return BelongsTo<InteractionDefinitionVersion, $this> */
    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(InteractionDefinitionVersion::class, 'draft_version_id');
    }

    public function activeVersionRecord(): ?InteractionDefinitionVersion
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
        $this->name = trim((string) $this->name);

        if ($this->name === '' || mb_strlen($this->name) > 120) {
            throw new LogicException('Interaction Definition name is required and may not exceed 120 characters.');
        }
        if (! in_array($this->status, self::STATUSES, true)) {
            throw new LogicException('Unknown Interaction Definition status.');
        }
        if ((int) $this->current_version < 1) {
            throw new LogicException('Interaction Definition version must be positive.');
        }

        Context::query()->findOrFail($this->context_id);

        if ($this->space_content_id !== null) {
            $content = SpaceContent::query()->findOrFail($this->space_content_id);

            if ((int) $content->context_id !== (int) $this->context_id) {
                throw new LogicException('Interaction Definition Content must belong to the same Context.');
            }
        }
    }
}
