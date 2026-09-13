<?php

namespace App\Actions\Groups;

use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveSpaceContent
{
    public function execute(SpaceContent $content, User $user): SpaceContent
    {
        return DB::transaction(function () use ($content, $user): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);

            if ($current->status === 'archived') {
                Gate::forUser($user)->authorize('view', $current);

                return $current;
            }

            Gate::forUser($user)->authorize('archive', $current);
            $current->applyLifecycle([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

            return $current->refresh();
        }, 3);
    }
}
