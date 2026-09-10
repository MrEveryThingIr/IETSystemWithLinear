<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'invited_by_actor_id', 'email', 'content', 'token', 'expires_at', 'max_uses', 'uses_count', 'revoked_at'])]
class GroupInvitation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'invited_by_actor_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(GroupInvitationAcceptance::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'source_invitation_id');
    }
}
