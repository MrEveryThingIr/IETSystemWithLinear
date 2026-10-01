<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Business extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'kind',
        'short_intro',
        'description',
        'founded_year',
        'status',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            $business->uuid ??= (string) Str::uuid();

            if (! $business->code) {
                do {
                    $code = 'BIZ-'.Str::upper(Str::random(8));
                } while (self::query()->where('code', $code)->exists());

                $business->code = $code;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Actor, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'owner_actor_id');
    }

    /** @return HasMany<BusinessMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    /** @return MorphMany<ContactPoint, $this> */
    public function contactPoints(): MorphMany
    {
        return $this->morphMany(ContactPoint::class, 'contactable');
    }

    /** @return MorphMany<ActorAddress, $this> */
    public function addresses(): MorphMany
    {
        return $this->morphMany(ActorAddress::class, 'addressable');
    }
}
