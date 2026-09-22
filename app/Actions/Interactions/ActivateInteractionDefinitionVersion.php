<?php

namespace App\Actions\Interactions;

use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ActivateInteractionDefinitionVersion
{
    public function execute(
        InteractionDefinition $definition,
        InteractionDefinitionVersion $version,
        User $user,
    ): InteractionDefinition {
        return DB::transaction(function () use ($definition, $version, $user): InteractionDefinition {
            $lockedDefinition = InteractionDefinition::query()
                ->lockForUpdate()
                ->findOrFail($definition->id);

            Gate::forUser($user)->authorize('activate', $lockedDefinition);

            $lockedVersion = InteractionDefinitionVersion::query()
                ->lockForUpdate()
                ->findOrFail($version->id);

            abort_unless(
                (int) $lockedVersion->interaction_definition_id === (int) $lockedDefinition->id,
                422,
                'Interaction Definition version does not belong to this Definition.',
            );

            $lockedVersion->publish();

            $lockedDefinition->applyLifecycle([
                'status' => InteractionDefinition::STATUS_ACTIVE,
                'current_version' => $lockedVersion->version,
                'active_version_id' => $lockedVersion->id,
                'draft_version_id' => null,
            ]);

            return $lockedDefinition->refresh()->load('activeVersion');
        }, 3);
    }
}
