<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddSpaceContentAnnotation
{
    public function execute(
        SpaceContent $content,
        SpaceContentRevision $revision,
        User $user,
        string $body,
        ?SpaceContentAnnotation $parent = null,
    ): SpaceContentAnnotation {
        $body = trim($body);
        abort_unless($body !== '' && mb_strlen($body) <= 5000, 422, 'Comment must contain between 1 and 5000 characters.');

        return DB::transaction(function () use ($content, $revision, $user, $body, $parent): SpaceContentAnnotation {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('interact', $current);

            abort_unless(
                $current->active_revision_id !== null
                    && (int) $current->active_revision_id === (int) $revision->id,
                409,
                'This published edition has changed. Refresh before interacting.',
            );

            $lockedRevision = SpaceContentRevision::query()
                ->whereKey($revision->id)
                ->where('space_content_id', $current->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedRevision->hasVerifiableManifest(), 409, 'Only verified published editions accept interactions.');

            $parentAnnotation = null;
            if ($parent instanceof SpaceContentAnnotation) {
                $parentAnnotation = SpaceContentAnnotation::query()->lockForUpdate()->findOrFail($parent->id);
                abort_unless(
                    (int) $parentAnnotation->space_content_id === (int) $current->id
                        && (int) $parentAnnotation->space_content_revision_id === (int) $lockedRevision->id
                        && $parentAnnotation->parent_annotation_id === null
                        && $parentAnnotation->kind === SpaceContentAnnotation::KIND_COMMENT
                        && $parentAnnotation->status === SpaceContentAnnotation::STATUS_ACTIVE,
                    422,
                    'Replies must target an active top-level comment on this edition.',
                );
            }

            return SpaceContentAnnotation::query()->create([
                'space_content_id' => $current->id,
                'space_content_revision_id' => $lockedRevision->id,
                'parent_annotation_id' => $parentAnnotation?->id,
                'author_actor_id' => $this->actor($user)->id,
                'kind' => SpaceContentAnnotation::KIND_COMMENT,
                'visibility' => SpaceContentAnnotation::VISIBILITY_SPACE,
                'status' => SpaceContentAnnotation::STATUS_ACTIVE,
                'body' => $body,
            ]);
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
