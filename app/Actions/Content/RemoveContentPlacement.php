<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\ContentPlacement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveContentPlacement
{
    public function execute(ContentPlacement $placement, User $user): ContentPlacement
    {
        return DB::transaction(function () use ($placement, $user): ContentPlacement {
            $current = ContentPlacement::query()
                ->with('context')
                ->lockForUpdate()
                ->findOrFail($placement->id);

            Gate::forUser($user)->authorize('manageContent', $current->context);

            if ($current->status === ContentPlacement::STATUS_ACTIVE) {
                $current->remove($this->actor($user));
            }

            return $current->refresh();
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
