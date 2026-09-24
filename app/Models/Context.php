<?php

namespace App\Models;

use App\ContextKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'kind'])]
class Context extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => ContextKind::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $context): void {
            if ($context->getAttribute('uuid') === null) {
                $context->setAttribute('uuid', (string) Str::uuid());
            }
        });

        static::updating(function (self $context): void {
            if ($context->isDirty(['uuid', 'kind'])) {
                throw new LogicException('Context identity and kind are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Contexts are durable domain identity and cannot be deleted directly.');
        });
    }

    /** @return HasOne<PersonalContext, $this> */
    public function personalBinding(): HasOne
    {
        return $this->hasOne(PersonalContext::class);
    }

    /** @return HasOne<GroupSpaceContext, $this> */
    public function groupSpaceBinding(): HasOne
    {
        return $this->hasOne(GroupSpaceContext::class);
    }

    /** @return HasOne<AdmissionContext, $this> */
    public function admissionBinding(): HasOne
    {
        return $this->hasOne(AdmissionContext::class);
    }

    /** @return HasOne<ReferenceContext, $this> */
    public function referenceBinding(): HasOne
    {
        return $this->hasOne(ReferenceContext::class);
    }

    /** @return HasMany<SpaceContentDefinition, $this> */
    public function contentDefinitions(): HasMany
    {
        return $this->hasMany(SpaceContentDefinition::class);
    }

    /** @return HasMany<SpaceContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(SpaceContent::class);
    }

    /** @return HasMany<ContentPlacement, $this> */
    public function contentPlacements(): HasMany
    {
        return $this->hasMany(ContentPlacement::class);
    }

    /** @return HasMany<SpaceContentRenderTemplate, $this> */
    public function renderTemplates(): HasMany
    {
        return $this->hasMany(SpaceContentRenderTemplate::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasMany<InteractionDefinition, $this> */
    public function interactionDefinitions(): HasMany
    {
        return $this->hasMany(InteractionDefinition::class);
    }
}
