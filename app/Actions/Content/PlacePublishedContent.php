<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\ContentPlacement;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PlacePublishedContent
{
    public function execute(SpaceContent $content, Context $target, User $user): ContentPlacement
    {
        return DB::transaction(function () use ($content, $target, $user): ContentPlacement {
            $current = SpaceContent::query()->with('context')->lockForUpdate()->findOrFail($content->id);
            $targetContext = Context::query()->lockForUpdate()->findOrFail($target->id);

            abort_if((int) $current->context_id === (int) $targetContext->id, 422, 'Content is already in its home Context.');

            Gate::forUser($user)->authorize('view', $current->context);
            Gate::forUser($user)->authorize('view', $current);
            Gate::forUser($user)->authorize('manageContent', $targetContext);

            $revision = $current->activeRevisionRecord();
            abort_unless(
                $current->status === 'published'
                    && $revision instanceof SpaceContentRevision
                    && $revision->hasVerifiableManifest(),
                422,
                'Only sealed published Content can be presented in another Context.',
            );

            $existing = ContentPlacement::query()
                ->where('context_id', $targetContext->id)
                ->where('space_content_id', $current->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ContentPlacement) {
                if ($existing->status === ContentPlacement::STATUS_REMOVED) {
                    $existing->activate();
                }

                return $existing->refresh();
            }

            return ContentPlacement::query()->create([
                'uuid' => (string) Str::uuid(),
                'context_id' => $targetContext->id,
                'space_content_id' => $current->id,
                'placed_by_actor_id' => $this->actor($user)->id,
                'status' => ContentPlacement::STATUS_ACTIVE,
                'removed_at' => null,
                'removed_by_actor_id' => null,
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
