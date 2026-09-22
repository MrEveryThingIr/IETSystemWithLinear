<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpaceContentAssetController extends Controller
{
    public function show(
        Request $request,
        Group $group,
        GroupSpace $space,
        SpaceContent $content,
        Asset $asset,
    ): StreamedResponse {
        $this->authorizeAsset($request, $group, $space, $content, $asset);

        return Storage::disk($asset->disk)->response(
            $asset->storage_key,
            $asset->original_filename,
            [
                'Content-Type' => $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline',
        );
    }

    public function download(
        Request $request,
        Group $group,
        GroupSpace $space,
        SpaceContent $content,
        Asset $asset,
    ): StreamedResponse {
        $this->authorizeAsset($request, $group, $space, $content, $asset);

        return Storage::disk($asset->disk)->download(
            $asset->storage_key,
            $asset->original_filename,
            [
                'Content-Type' => $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function authorizeAsset(
        Request $request,
        Group $group,
        GroupSpace $space,
        SpaceContent $content,
        Asset $asset,
    ): void {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        abort_unless(
            $asset->group_space_id !== null
                && (int) $asset->group_space_id === (int) $space->id
                && (int) $asset->context_id === (int) $content->context_id,
            404,
        );

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        Gate::forUser($user)->authorize('view', $content);

        $revisionIds = Gate::forUser($user)->allows('revisions', $content)
            ? $content->revisions()->pluck('id')
            : collect([$content->active_revision_id])->filter();

        $linkedToRevision = DB::table('space_content_revision_assets')
            ->where('asset_id', $asset->id)
            ->whereIn('space_content_revision_id', $revisionIds)
            ->exists();

        $linkedToVisibleAnnotation = false;
        if (! $linkedToRevision) {
            $currentUser = User::query()->with('actor')->find($user->id);
            abort_unless($currentUser instanceof User && $currentUser->actor instanceof Actor, 403);

            $linkedToVisibleAnnotation = DB::table('space_content_annotation_assets as placement')
                ->join('space_content_annotations as annotation', 'annotation.id', '=', 'placement.annotation_id')
                ->where('placement.asset_id', $asset->id)
                ->where('annotation.space_content_id', $content->id)
                ->whereIn('annotation.space_content_revision_id', $revisionIds)
                ->where('annotation.status', SpaceContentAnnotation::STATUS_ACTIVE)
                ->where(function ($query) use ($currentUser): void {
                    $query->where('annotation.visibility', SpaceContentAnnotation::VISIBILITY_SPACE)
                        ->orWhere(function ($query) use ($currentUser): void {
                            $query->where('annotation.visibility', SpaceContentAnnotation::VISIBILITY_PRIVATE)
                                ->where('annotation.author_actor_id', $currentUser->actor->id);
                        });
                })
                ->exists();
        }

        abort_unless($linkedToRevision || $linkedToVisibleAnnotation, 404);
        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);
    }
}
