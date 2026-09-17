<?php

namespace App\Actions\Groups;

use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DiscardSpaceContentDefinitionDraft
{
    public function execute(SpaceContentDefinition $definition, User $user): SpaceContentDefinition
    {
        return DB::transaction(function () use ($definition, $user): SpaceContentDefinition {
            $current = SpaceContentDefinition::query()->with('space')->lockForUpdate()->findOrFail($definition->id);
            Gate::forUser($user)->authorize('manage', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content Definitions cannot discard drafts.');

            $active = $current->activeVersionRecord();
            abort_unless($active instanceof SpaceContentDefinitionVersion, 422, 'An initial Definition draft must be archived rather than discarded.');

            $draft = $current->draftVersionRecord();
            if (! $draft instanceof SpaceContentDefinitionVersion) {
                return $current;
            }

            $draft = SpaceContentDefinitionVersion::query()->lockForUpdate()->findOrFail($draft->id);
            abort_if($draft->published_at !== null, 422, 'Published Definition versions cannot be discarded.');
            abort_if($draft->revisions()->exists(), 422, 'Referenced Definition versions cannot be discarded.');

            $current->applyLifecycle([
                'status' => 'active',
                'current_version' => $active->version,
                'active_version_id' => $active->id,
                'draft_version_id' => null,
            ]);

            $draft->discard();

            return $current->refresh();
        }, 3);
    }
}
