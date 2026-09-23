<?php

namespace App\Actions\Access;

use App\Models\AccessInvitation;
use App\Models\Actor;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueAccessInvitation
{
    public function execute(
        User $issuer,
        ?string $email = null,
        int $maxUses = 1,
        int $expiresInDays = 14,
    ): AccessInvitation {
        abort_unless($issuer->hasPlatformCapability(PlatformCapability::ManageUsers), 403);

        $email = $email !== null ? Str::lower(trim($email)) : null;
        $email = $email === '' ? null : $email;

        abort_unless($maxUses >= 1 && $maxUses <= 1000, 422, __('access.invalid_use_limit'));
        abort_unless($expiresInDays >= 1 && $expiresInDays <= 90, 422, __('access.invalid_expiry'));

        if ($email !== null) {
            abort_if(
                User::query()->whereRaw('LOWER(email) = ?', [$email])->exists(),
                422,
                __('access.already_registered'),
            );
        }

        $actor = Actor::query()->where('user_id', $issuer->id)->firstOrFail();

        return DB::transaction(
            fn (): AccessInvitation => AccessInvitation::query()->create([
                'invited_by_actor_id' => $actor->id,
                'email' => $email,
                'token' => AccessInvitation::issueToken(),
                'expires_at' => now()->addDays($expiresInDays),
                'max_uses' => $maxUses,
                'uses_count' => 0,
            ]),
            3,
        );
    }
}
