<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Fillable(['group_id', 'invited_by_actor_id', 'email', 'content', 'join_mode', 'granted_role_id', 'label', 'allows_registration', 'token', 'expires_at', 'max_uses', 'uses_count', 'revoked_at'])]
class GroupInvitation extends Model
{
    use HasFactory;

    public const JOIN_REVIEW = 'review';

    public const JOIN_DIRECT = 'direct';

    public const JOIN_MODES = [self::JOIN_REVIEW, self::JOIN_DIRECT];

    protected $hidden = ['token'];

    private ?string $plainTextToken = null;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function issueToken(): string
    {
        return Str::random(64);
    }

    public function setTokenAttribute(string $token): void
    {
        $this->plainTextToken = $token;
        $this->attributes['token'] = self::hashToken($token);
    }

    public function getTokenAttribute(?string $value): ?string
    {
        return $this->plainTextToken;
    }

    public function plainTextToken(): ?string
    {
        return $this->plainTextToken;
    }

    public function maskedEmail(): ?string
    {
        if ($this->email === null) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return Str::substr($local, 0, 1).str_repeat('•', max(3, Str::length($local) - 1)).'@'.$domain;
    }

    public function admitsDirectly(): bool
    {
        return $this->join_mode === self::JOIN_DIRECT;
    }

    /** New accounts may be created only through reserved or explicitly opened links. */
    public function permitsRegistration(): bool
    {
        return $this->email !== null || $this->allows_registration;
    }

    public function isAvailable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->uses_count < $this->max_uses);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'allows_registration' => 'boolean'];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function grantedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'granted_role_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'invited_by_actor_id');
    }

    /** @return HasMany<GroupInvitationAcceptance, $this> */
    public function acceptances(): HasMany
    {
        return $this->hasMany(GroupInvitationAcceptance::class);
    }

    /** @return HasMany<Admission, $this> */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'source_invitation_id');
    }
}
