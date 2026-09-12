<?php

namespace App\Actions\Platform;

use App\Models\PlatformAccessRequest;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestPlatformAccess
{
    public function execute(User $user, PlatformRole $role, ?string $reason = null): PlatformAccessRequest
    {
        abort_unless($role->isRequestable(), 422, 'This platform role cannot be self-requested.');

        return DB::transaction(function () use ($user, $role, $reason): PlatformAccessRequest {
            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            abort_unless($lockedUser->status === 'active' && $lockedUser->email_verified_at !== null, 403);
            abort_if($this->alreadyHasRoleCapabilities($lockedUser, $role), 422, 'This access is already granted.');

            $pendingKey = $lockedUser->id.':'.$role->value;
            $existing = PlatformAccessRequest::query()
                ->where('pending_key', $pendingKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PlatformAccessRequest) {
                return $existing;
            }

            return PlatformAccessRequest::create([
                'user_id' => $lockedUser->id,
                'role' => $role,
                'status' => 'pending',
                'pending_key' => $pendingKey,
                'reason' => $reason,
                'correlation_id' => (string) Str::uuid(),
            ]);
        }, attempts: 3);
    }

    private function alreadyHasRoleCapabilities(User $user, PlatformRole $role): bool
    {
        foreach ($role->capabilities() as $capability) {
            if (! $user->hasPlatformCapability($capability)) {
                return false;
            }
        }

        return true;
    }
}
