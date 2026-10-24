<?php

namespace App\Models;

use App\Support\Parties\Party;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Something a person, business or group can do, under a category. It records
 * ability (level, experience, self-declared confidence) — never a price; prices
 * belong to Offers.
 */
#[Fillable([
    'uuid',
    'holder_actor_id',
    'holder_business_id',
    'holder_group_id',
    'business_membership_id',
    'concept_id',
    'title',
    'description',
    'level',
    'years_experience',
    'self_confidence_percent',
    'is_primary',
    'visibility',
    'status',
    'sort_order',
    'created_by_actor_id',
    'legacy_actor_profession_id',
    'legacy_membership_profession_id',
])]
class Capability extends Model
{
    public const LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];

    /** "contacts" is kept for old records and treated as private until contacts exist. */
    public const VISIBILITIES = ['private', 'contacts', 'members', 'public'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $attributes = [
        'visibility' => 'private',
        'status' => self::STATUS_ACTIVE,
        'is_primary' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'years_experience' => 'integer',
            'self_confidence_percent' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $capability): void {
            $capability->uuid ??= (string) Str::uuid();
            $holders = array_filter([$capability->holder_actor_id, $capability->holder_business_id, $capability->holder_group_id], fn ($id): bool => $id !== null);
            if (count($holders) !== 1) {
                throw new LogicException('A capability has exactly one holder.');
            }
            if ($capability->exists && $capability->isDirty(['uuid', 'holder_actor_id', 'holder_business_id', 'holder_group_id', 'created_by_actor_id'])) {
                throw new LogicException('A capability keeps its holder and author.');
            }
        });
    }

    public function holder(): Party
    {
        $party = Party::fromRecord($this, 'holder');
        if (! $party instanceof Party) {
            throw new LogicException('Capability without holder.');
        }

        return $party;
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function holderActor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'holder_actor_id');
    }

    /** @return BelongsTo<Business, $this> */
    public function holderBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'holder_business_id');
    }

    /** @return BelongsTo<Group, $this> */
    public function holderGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'holder_group_id');
    }

    /** @return BelongsTo<BusinessMembership, $this> */
    public function businessMembership(): BelongsTo
    {
        return $this->belongsTo(BusinessMembership::class);
    }

    /** @param Builder<Capability> $query */
    public function scopeHeldBy(Builder $query, Party $party): void
    {
        $query->where($party->columns('holder'));
    }

    /** @param Builder<Capability> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function displayTitle(?string $locale = null): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : $this->concept->canonical()->displayLabel($locale);
    }
}
