<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Something a person cares about, in one category, with what they want from
 * it (follow, learn, enjoy, work in, join others, create). Private to its
 * holder unless they choose to show it on their profile.
 *
 * @property int $id
 * @property string $uuid
 * @property int $holder_actor_id
 * @property int $concept_id
 * @property list<string> $purposes
 * @property string|null $note
 * @property string $visibility
 * @property int $created_by_actor_id
 */
#[Fillable([
    'holder_actor_id',
    'concept_id',
    'purposes',
    'note',
    'visibility',
    'created_by_actor_id',
])]
class Interest extends Model
{
    public const PURPOSES = ['follow', 'learn', 'enjoy', 'work_in', 'join_others', 'create'];

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_PROFILE = 'profile';

    public const VISIBILITIES = [self::VISIBILITY_PRIVATE, self::VISIBILITY_PROFILE];

    protected $attributes = [
        'visibility' => self::VISIBILITY_PRIVATE,
    ];

    protected function casts(): array
    {
        return [
            'purposes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $interest): void {
            $interest->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $interest): void {
            $purposes = self::normalizePurposes((array) $interest->purposes);
            if ($purposes === []) {
                throw new LogicException('An interest needs at least one purpose.');
            }
            $interest->purposes = $purposes;
            if (! in_array($interest->visibility, self::VISIBILITIES, true)) {
                throw new LogicException('Unknown interest visibility.');
            }
        });

        static::updating(function (self $interest): void {
            if ($interest->isDirty(['uuid', 'holder_actor_id', 'created_by_actor_id'])) {
                throw new LogicException('An interest cannot move to another person.');
            }
        });
    }

    /**
     * Known purposes only, once each, in the fixed order.
     *
     * @param  array<int, mixed>  $purposes
     * @return list<string>
     */
    public static function normalizePurposes(array $purposes): array
    {
        $given = array_map('strval', $purposes);

        return array_values(array_filter(self::PURPOSES, fn (string $purpose): bool => in_array($purpose, $given, true)));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Actor, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'holder_actor_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @param Builder<Interest> $query */
    public function scopeHeldBy(Builder $query, Actor|int $actor): void
    {
        $query->where('holder_actor_id', $actor instanceof Actor ? $actor->id : $actor);
    }

    /** @param Builder<Interest> $query */
    public function scopeOnProfile(Builder $query): void
    {
        $query->where('visibility', self::VISIBILITY_PROFILE);
    }

    public function hasPurpose(string $purpose): bool
    {
        return in_array($purpose, $this->purposes, true);
    }
}
