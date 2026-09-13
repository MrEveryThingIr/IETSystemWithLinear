<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviseSpaceContentDefinition
{
    public function execute(SpaceContentDefinition $definition, User $user): SpaceContentDefinition
    {
        return DB::transaction(function () use ($definition, $user): SpaceContentDefinition {
            $current = SpaceContentDefinition::query()->with('space')->lockForUpdate()->findOrFail($definition->id);
            Gate::forUser($user)->authorize('manage', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content Definitions cannot be revised.');

            if ($current->draftVersionRecord() instanceof SpaceContentDefinitionVersion) {
                return $current;
            }

            $active = $current->activeVersionRecord();
            abort_unless($active instanceof SpaceContentDefinitionVersion && $active->published_at !== null, 422, 'An active published Definition version is required before creating a replacement draft.');

            $nextVersion = ((int) $current->versions()->max('version')) + 1;
            $actor = $this->actor($user);

            $draft = $current->versions()->create([
                'version' => $nextVersion,
                'schema' => $active->schema,
                'display' => $active->display,
                'created_by_actor_id' => $actor->id,
                'published_at' => null,
            ]);

            $current->applyLifecycle([
                'status' => 'draft',
                'current_version' => $nextVersion,
                'active_version_id' => $active->id,
                'draft_version_id' => $draft->id,
            ]);

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
