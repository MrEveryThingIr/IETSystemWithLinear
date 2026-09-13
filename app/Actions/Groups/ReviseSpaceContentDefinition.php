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

            if ($current->status === 'draft') {
                return $current;
            }

            /** @var SpaceContentDefinitionVersion $published */
            $published = $current->versions()
                ->where('version', $current->current_version)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($published->published_at !== null, 422, 'The active Content Definition must reference a published version.');

            $nextVersion = $current->current_version + 1;
            $actor = $this->actor($user);

            $current->versions()->create([
                'version' => $nextVersion,
                'schema' => $published->schema,
                'display' => $published->display,
                'created_by_actor_id' => $actor->id,
                'published_at' => null,
            ]);
            $current->applyLifecycle([
                'status' => 'draft',
                'current_version' => $nextVersion,
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
