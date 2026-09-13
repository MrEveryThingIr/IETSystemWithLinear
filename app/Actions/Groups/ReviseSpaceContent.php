<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviseSpaceContent
{
    /** @param array<string, mixed> $payload */
    public function execute(SpaceContent $content, User $user, string $title, array $payload): SpaceContent
    {
        $title = trim($title);
        abort_if($title === '' || mb_strlen($title) > 255, 422, 'Content title is required and may not exceed 255 characters.');

        return DB::transaction(function () use ($content, $user, $title, $payload): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_unless($current->status === 'draft', 422, 'Only draft Content may be revised.');

            $currentRevision = $current->revisions()
                ->where('revision', $current->current_revision)
                ->lockForUpdate()
                ->firstOrFail();
            /** @var SpaceContentDefinitionVersion $definitionVersion */
            $definitionVersion = SpaceContentDefinitionVersion::query()->findOrFail($currentRevision->definition_version_id);
            $normalizedPayload = SpaceContentSchema::normalizePayload($definitionVersion->schema, $payload);
            $nextRevision = $current->current_revision + 1;
            $actor = $this->actor($user);

            $current->revisions()->create([
                'definition_version_id' => $definitionVersion->id,
                'revision' => $nextRevision,
                'title' => $title,
                'payload' => $normalizedPayload,
                'created_by_actor_id' => $actor->id,
            ]);
            $current->applyLifecycle(['current_revision' => $nextRevision]);

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
