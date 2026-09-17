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

            $draft = $current->draftVersionRecord();

            if (! $draft instanceof SpaceContentDefinitionVersion) {
                $active = $current->activeVersionRecord();
                abort_unless($active instanceof SpaceContentDefinitionVersion, 422, 'There is no Definition version to activate.');

                return $current;
            }

            $draft = SpaceContentDefinitionVersion::query()->lockForUpdate()->findOrFail($draft->id);
            $draft->publish();

            $current->applyLifecycle([
                'status' => 'active',
                'current_version' => $draft->version,
                'active_version_id' => $draft->id,
                'draft_version_id' => null,
            ]);

            return $current->refresh();
        }, 3);
    }
}
