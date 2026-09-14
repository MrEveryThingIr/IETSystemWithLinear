<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentRevisionComposition;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviseSpaceContent
{
    public function __construct(private readonly SpaceContentRevisionComposition $composition) {}

    /** @param array<string, mixed> $payload */
    public function execute(SpaceContent $content, User $user, string $title, array $payload): SpaceContent
    {
        $title = trim($title);
        abort_if($title === '' || mb_strlen($title) > 255, 422, 'Content title is required and may not exceed 255 characters.');

        return DB::transaction(function () use ($content, $user, $title, $payload): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content cannot be revised.');

            $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($source instanceof SpaceContentRevision, 422, 'Content has no revision to revise from.');
            $source = SpaceContentRevision::query()->lockForUpdate()->findOrFail($source->id);

            /** @var SpaceContentDefinitionVersion $definitionVersion */
            $definitionVersion = SpaceContentDefinitionVersion::query()->findOrFail($source->definition_version_id);
            $normalizedPayload = SpaceContentSchema::normalizePayload($definitionVersion->schema, $payload);
            $nextRevision = ((int) $current->revisions()->max('revision')) + 1;
            $actor = $this->actor($user);

            $revision = $current->revisions()->create([
                'definition_version_id' => $definitionVersion->id,
                'revision' => $nextRevision,
                'title' => $title,
                'payload' => $normalizedPayload,
                'render_template_key' => $source->render_template_key,
                'render_template_uuid' => $source->render_template_uuid,
                'presentation' => $source->presentation,
                'created_by_actor_id' => $actor->id,
            ]);

            $this->composition->copyAssets($source, $revision);
            $this->composition->copyRelationships($source, $revision);

            $current->applyLifecycle([
                'current_revision' => $nextRevision,
                'draft_revision_id' => $revision->id,
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
