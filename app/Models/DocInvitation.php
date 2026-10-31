<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A link that opens the documentation while it is in invite-only mode. Only a hash of the token is stored. */
class DocInvitation extends Model
{
    protected $fillable = ['label', 'token_hash', 'expires_at', 'max_uses', 'uses_count', 'revoked_at', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->uuid ??= (string) Str::uuid();
        });
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->uses_count < $this->max_uses);
    }
}
