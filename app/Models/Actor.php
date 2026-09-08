<?php

namespace App\Models;

use Database\Factories\ActorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class Actor extends Model
{
    /** @use HasFactory<ActorFactory> */
    use HasFactory, HasRoles;

    protected string $guard_name = 'web';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<GroupMembership, $this> */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /** @return HasMany<Group, $this> */
    public function createdGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'created_by_actor_id');
    }
}
