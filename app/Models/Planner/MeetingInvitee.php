<?php

namespace App\Models\Planner;

use App\Models\Actor;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Someone asked to a meeting: a user (actor_id) or a guest reached by a
 * private link (token). A guest who registers later can claim it.
 */
#[Fillable([
    'uuid', 'meeting_request_id', 'actor_id', 'name', 'email', 'token_hash', 'token_encrypted',
    'status', 'note', 'plan_id', 'responded_at', 'claimed_at', 'last_viewed_at',
])]
#[Hidden(['token_hash', 'token_encrypted'])]
class MeetingInvitee extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_DECLINED = 'declined';

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'claimed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitee): void {
            $invitee->uuid ??= (string) Str::uuid();
        });
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    public function token(): ?string
    {
        if ($this->token_encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($this->token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isGuest(): bool
    {
        return $this->actor_id === null;
    }

    /** @return BelongsTo<MeetingRequest, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<MeetingVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(MeetingVote::class);
    }
}
