<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateSpaceContent
{
    /** @param array<string, mixed> $payload */
    public function execute(
        GroupSpace $space,
        SpaceContentDefinition $definition,
        User $user,
        string $title,
        array $payload,
    ): SpaceContent {
        $title = trim($title);
        abort_if($title === '' || mb_strlen($title) > 255, 422, 'Content title is required and may not exceed 255 characters.');

        return DB::transaction(function () use ($space, $definition, $user, $title, $payload): SpaceContent {
            $currentSpace = GroupSpace::query()->lockForUpdate()->findOrFail($space->id);
            Gate::forUser($user)->authorize('create', [SpaceContent::class, $currentSpace]);

            $currentDefinition = SpaceContentDefinition::query()->lockForUpdate()->findOrFail($definition->id);
            abort_unless((int) $currentDefinition->group_space_id === (int) $currentSpace->id, 404);
            abort_unless($currentDefinition->status === 'active', 422, 'Content may only be created from an active Content Definition.');

            /** @var SpaceContentDefinitionVersion $version */
            $version = $currentDefinition->versions()
                ->where('version', $currentDefinition->current_version)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($version->published_at !== null, 422, 'The active Content Definition version must be published.');

            $normalizedPayload = SpaceContentSchema::normalizePayload($version->schema, $payload);
            $actor = $this->actor($user);

            $content = $currentSpace->contents()->create([
                'space_content_definition_id' => $currentDefinition->id,
                'author_actor_id' => $actor->id,
                'status' => 'draft',
                'current_revision' => 1,
                'published_at' => null,
                'archived_at' => null,
            ]);

            $content->revisions()->create([
                'definition_version_id' => $version->id,
                'revision' => 1,
                'title' => $title,
                'payload' => $normalizedPayload,
                'created_by_actor_id' => $actor->id,
            ]);

            return $content->refresh();
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
