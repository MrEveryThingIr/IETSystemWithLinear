<?php

namespace App\Actions\Groups;

use App\Models\GroupInvitation;
use Illuminate\Support\Facades\DB;

class RevokeGroupInvitation
{
    public function execute(GroupInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = GroupInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->revoked_at === null) {
                $locked->update(['revoked_at' => now()]);
            }
        });
    }
}
