<?php

namespace App\Models;

use App\DomainJourneyKind;
use App\Support\DomainBlueprintConfig;
use Database\Factories\DomainBlueprintVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'domain_blueprint_id',
    'version',
    'journey_kind',
    'terminology',
    'capabilities',
    'content_blueprint_slugs',
    'guided_entry',
    'created_by_actor_id',
    'content_hash',
    'published_at',
])]
class DomainBlueprintVersion extends Model
{
    /** @use HasFactory<DomainBlueprintVersionFactory> */
    use HasFactory;

    private bool $publishing = false;

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
            $version->normalizeConfiguration();
        });

        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') !== null) {
                throw new LogicException('Published Domain Blueprint versions are immutable.');
            }

            if ($version->isDirty([
                'uuid',
                'domain_blueprint_id',
                'version',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Domain Blueprint version provenance cannot change.');
            }

            if ($version->isDirty('published_at') && ! $version->publishing) {
                throw new LogicException('Domain Blueprint versions must be published through the lifecycle method.');
            }

            if ($version->isDirty([
                'journey_kind',
                'terminology',
                'capabilities',
                'content_blueprint_slugs',
                'guided_entry',
            ])) {
                $version->normalizeConfiguration();
            } elseif ($version->isDirty('content_hash')) {
                throw new LogicException('Domain Blueprint version hashes are application-managed.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Domain Blueprint versions preserve instantiated journey provenance.');
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

    public function recommends(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    /** @return BelongsTo<DomainBlueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(DomainBlueprint::class, 'domain_blueprint_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<Relationship, $this> */
    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }

    /** @return HasMany<Plan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    private function normalizeConfiguration(): void
    {
        $normalized = app(DomainBlueprintConfig::class)->normalize(
            $this->journey_kind,
            $this->terminology ?? [],
            $this->capabilities ?? [],
            $this->content_blueprint_slugs ?? [],
            $this->guided_entry ?? [],
        );

        foreach ($normalized as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    protected function casts(): array
    {
        return [
            'journey_kind' => DomainJourneyKind::class,
            'terminology' => 'array',
            'capabilities' => 'array',
            'content_blueprint_slugs' => 'array',
            'guided_entry' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
