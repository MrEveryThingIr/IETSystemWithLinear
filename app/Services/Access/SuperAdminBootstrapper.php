<?php

namespace App\Services\Access;

use App\Models\Actor;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SuperAdminBootstrapper
{
    public function ensure(?string $identifier = null): User
    {
        $canonical = PlatformAccessGrant::query()
            ->with('user')
            ->active()
            ->where('role', PlatformRole::Superadmin->value)
            ->whereHas('user', fn ($query) => $query
                ->where('status', 'active')
                ->whereNotNull('email_verified_at'))
            ->oldest('id')
            ->first();

        if ($canonical?->user) {
            return $canonical->user;
        }

        $identifier = trim((string) ($identifier ?: config('iet_bootstrap.superadmin_identifier')));

        if ($identifier === '') {
            throw new RuntimeException(
                'IET initialization refused: no active platform super-admin exists. '.
                'Provide --superadmin=<email-or-username> or set IET_SUPERADMIN_IDENTIFIER.'
            );
        }

        $user = $this->findUser($identifier);
        $this->grant($user, null, 'IET initialization bootstrap.');

        return $user;
    }

    public function findUser(string $identifier): User
    {
        $query = User::query()->where('email', $identifier);

        if (Schema::hasColumn('users', 'username')) {
            $query->orWhere('username', $identifier);
        }

        $user = $query->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'superadmin' => "No existing user matches [{$identifier}].",
            ]);
        }

        return $this->ensureEligible($user);
    }

    public function grant(
        User $user,
        ?User $grantedBy = null,
        string $reason = 'Manual platform super-admin grant.'
    ): PlatformAccessGrant {
        $this->ensureEligible($user);

        return DB::transaction(function () use ($user, $grantedBy, $reason): PlatformAccessGrant {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->ensureEligible($lockedUser);

            $existing = PlatformAccessGrant::query()
                ->active()
                ->where('user_id', $lockedUser->getKey())
                ->where('role', PlatformRole::Superadmin->value)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PlatformAccessGrant) {
                return $existing;
            }

            if (! $lockedUser->actor()->exists()) {
                $actor = new Actor;
                $actor->user()->associate($lockedUser);
                $actor->save();
            }

            $grant = new PlatformAccessGrant;
            $grant->user()->associate($lockedUser);
            $grant->grantedBy()->associate($grantedBy);
            $grant->role = PlatformRole::Superadmin;
            $grant->granted_at = now();
            $grant->reason = $reason;
            $grant->correlation_id = (string) Str::uuid();
            $grant->save();

            return $grant;
        }, attempts: 3);
    }

    public function revoke(User $user, ?User $revokedBy = null): void
    {
        DB::transaction(function () use ($user, $revokedBy): void {
            $grant = PlatformAccessGrant::query()
                ->active()
                ->where('user_id', $user->getKey())
                ->where('role', PlatformRole::Superadmin->value)
                ->lockForUpdate()
                ->first();

            if (! $grant instanceof PlatformAccessGrant) {
                return;
            }

            $effectiveCount = PlatformAccessGrant::query()
                ->active()
                ->where('role', PlatformRole::Superadmin->value)
                ->whereHas('user', fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNotNull('email_verified_at'))
                ->lockForUpdate()
                ->count();

            if ($this->isEligible($user) && $effectiveCount <= 1) {
                throw new RuntimeException('Cannot revoke the last active platform super-admin.');
            }

            $grant->revokedBy()->associate($revokedBy);
            $grant->revoked_at = now();
            $grant->reason = trim($grant->reason."\nRevoked through the IET administration invariant.");
            $grant->save();
        }, attempts: 3);
    }

    private function ensureEligible(User $user): User
    {
        if (! $this->isEligible($user)) {
            throw ValidationException::withMessages([
                'superadmin' => 'A platform super-admin must have an active, verified user account.',
            ]);
        }

        return $user;
    }

    private function isEligible(User $user): bool
    {
        return $user->status === 'active' && $user->email_verified_at !== null;
    }
}
