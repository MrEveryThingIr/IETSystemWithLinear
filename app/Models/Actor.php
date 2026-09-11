<?php

namespace App\Models;

use Database\Factories\ActorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

class Actor extends Model
{
    /** @use HasFactory<ActorFactory> */
    use HasFactory, HasRoles;

    protected string $guard_name = 'web';

    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::updating(function (Actor $actor): void {
            if ($actor->isDirty('user_id')) {
                throw new LogicException('Actor identity links cannot be changed through ordinary model updates.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Actors cannot be deleted; archive them instead.');
        });
    }

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

    /** @return HasMany<Admission, $this> */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'candidate_actor_id');
    }

    /** @return HasMany<Group, $this> */
    public function createdGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'created_by_actor_id');
    }

    /** @return HasMany<GroupInvitation, $this> */
    public function sentGroupInvitations(): HasMany
    {
        return $this->hasMany(GroupInvitation::class, 'invited_by_actor_id');
    }

    /** @return HasMany<GroupInvitationAcceptance, $this> */
    public function acceptedGroupInvitations(): HasMany
    {
        return $this->hasMany(GroupInvitationAcceptance::class, 'accepted_by_actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
