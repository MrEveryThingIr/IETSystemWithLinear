<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessMembership extends Model
{
    protected $fillable = [
        'business_id',
        'actor_id',
        'role',
        'job_title',
        'status',
        'joined_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsToMany<Profession, $this> */
    public function professions(): BelongsToMany
    {
        return $this->belongsToMany(
            Profession::class,
            'business_membership_profession'
        )
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
