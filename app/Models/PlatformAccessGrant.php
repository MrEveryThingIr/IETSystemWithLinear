<?php

namespace App\Models;

use App\PlatformRole;
use Database\Factories\PlatformAccessGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAccessGrant extends Model
{
    /** @use HasFactory<PlatformAccessGrantFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /** @param Builder<PlatformAccessGrant> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
