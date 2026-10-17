<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A personal link that lets one invitee read one piece of Content without an
 * account. The plain token is only ever kept encrypted (so the author can copy
 * the link again); lookups use its SHA-256 hash.
 *
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'uuid',
    'space_content_id',
    'created_by_actor_id',
    'token_hash',
    'token_encrypted',
    'invitee_name',
    'message',
    'expires_at',
    'revoked_at',
    'revoked_by_actor_id',
    'view_count',
    'first_viewed_at',
    'last_viewed_at',
])]
class ContentInvitation extends Model
{
    public const TOKEN_LENGTH = 40;

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        if (strlen($token) !== self::TOKEN_LENGTH || ! ctype_alnum($token)) {
            return null;
        }

        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    public function token(): string
    {
        return (string) $this->token_encrypted;
    }

    public function url(): string
    {
        return route('content-invitations.show', $this->token());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }
}
