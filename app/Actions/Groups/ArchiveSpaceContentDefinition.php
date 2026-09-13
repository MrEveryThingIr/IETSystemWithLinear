<?php

namespace App\Actions\Groups;

use App\Models\SpaceContentDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveSpaceContentDefinition
{
    public function execute(SpaceContentDefinition $definition, User $user): SpaceContentDefinition
    {
        return DB::transaction(function () use ($definition, $user): SpaceContentDefinition {
            $current = SpaceContentDefinition::query()->with('space')->lockForUpdate()->findOrFail($definition->id);
            Gate::forUser($user)->authorize('manage', $current);

            if ($current->status === 'archived') {
                return $current;
            }

            abort_if($current->contents()->exists(), 422, 'A Content Definition already used by Content cannot be archived in this phase.');
            $current->applyLifecycle(['status' => 'archived']);

            return $current->refresh();
        }, 3);
    }
}
