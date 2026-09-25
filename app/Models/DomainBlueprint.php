<?php

namespace App\Models;

use Database\Factories\DomainBlueprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'slug',
    'name',
    'description',
    'category',
    'status',
    'current_version',
])]
class DomainBlueprint extends Model
{
    /** @use HasFactory<DomainBlueprintFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    protected static function booted(): void
    {
        static::creating(function (self $blueprint): void {
            $blueprint->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $blueprint): void {
            if ($blueprint->isDirty(['uuid', 'slug'])) {
                throw new LogicException('Domain Blueprint identity cannot change.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Domain Blueprints preserve journey provenance and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<DomainBlueprintVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DomainBlueprintVersion::class);
    }

    public function activeVersionRecord(): ?DomainBlueprintVersion
    {
        if ($this->current_version < 1) {
            return null;
        }

        return $this->versions()
            ->where('version', $this->current_version)
            ->whereNotNull('published_at')
            ->first();
    }

    protected function casts(): array
    {
        return ['current_version' => 'integer'];
    }
}
