<?php

namespace App\Actions\Groups;

use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ActivateSpaceContentDefinition
{
    public function execute(SpaceContentDefinition $definition, User $user): SpaceContentDefinition
    {
        return DB::transaction(function () use ($definition, $user): SpaceContentDefinition {
            $current = SpaceContentDefinition::query()->with('space')->lockForUpdate()->findOrFail($definition->id);
            Gate::forUser($user)->authorize('manage', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content Definitions cannot be activated.');

            /** @var SpaceContentDefinitionVersion $version */
            $version = $current->versions()
                ->where('version', $current->current_version)
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->status === 'active' && $version->published_at !== null) {
                return $current;
            }

            $version->publish();
            $current->applyLifecycle(['status' => 'active']);

            return $current->refresh();
        }, 3);
    }
}
