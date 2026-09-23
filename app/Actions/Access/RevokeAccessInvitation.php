<?php
namespace App\Actions\Access;
use App\Models\AccessInvitation;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Facades\DB;
class RevokeAccessInvitation
{
    public function execute(User $user, AccessInvitation $invitation): AccessInvitation
    {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageUsers),403);
        return DB::transaction(function () use ($invitation): AccessInvitation {
            $locked=AccessInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->revoked_at===null) { $locked->forceFill(['revoked_at'=>now()])->save(); }
            return $locked->refresh();
        },3);
    }
}
