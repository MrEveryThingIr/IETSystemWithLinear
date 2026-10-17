<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\AssetMediaTrack;
use App\Models\AssetMediaVariant;
use App\Models\Context;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\User;
use App\Support\AssetHttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SpaceContentAssetController extends Controller
{
    public function show(
        Request $request,
        Group $group,
        GroupSpace $space,
        SpaceContent $content,
        Asset $asset,
    ): Response {
        $this->authorizeAsset($request, $group, $space, $content, $asset);

        return AssetHttpResponse::inline($asset);
    }

    public function download(
        Request $request,
        Group $group,
        GroupSpace $space,
        SpaceContent $content,
        Asset $asset,
    ): Response {
        $this->authorizeAsset($request, $group, $space, $content, $asset);

        return AssetHttpResponse::download($asset);
    }

    public function showContext(
        Request $request,
        Context $context,
        SpaceContent $content,
        Asset $asset,
    ): Response {
        $this->authorizeContextAsset($request, $context, $content, $asset);

        return AssetHttpResponse::inline($asset);
    }

    public function downloadContext(
        Request $request,
        Context $context,
        SpaceContent $content,
        Asset $asset,
    ): Response {
        $this->authorizeContextAsset($request, $context, $content, $asset);

        return AssetHttpResponse::download($asset);
    }

    /** A derivative of a Content asset (for example the video poster frame). */
    public function variantContext(
        Request $request,
        Context $context,
        SpaceContent $content,
        Asset $asset,
        AssetMediaVariant $variant,
    ): Response {
        abort_unless((int) $variant->asset_id === (int) $asset->id && $variant->storage_key !== null, 404);
        $this->authorizeContextAsset($request, $context, $content, $asset);
        abort_unless(Storage::disk((string) $variant->disk)->exists($variant->storage_key), 404);

        return Storage::disk((string) $variant->disk)->response($variant->storage_key, null, [
            'Content-Type' => (string) $variant->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** A WebVTT captions track of a Content audio/video asset. */
    public function trackContext(
        Request $request,
        Context $context,
        SpaceContent $content,
        Asset $asset,
        AssetMediaTrack $track,
    ): Response {
        abort_unless((int) $track->asset_id === (int) $asset->id, 404);
        $this->authorizeContextAsset($request, $context, $content, $asset);
        abort_unless(Storage::disk($track->disk)->exists($track->storage_key), 404);

        return Storage::disk($track->disk)->response($track->storage_key, null, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeContextAsset(
        Request $request,
        Context $context,
        SpaceContent $content,
        Asset $asset,
    ): void {
        abort_unless(
            (int) $content->context_id === (int) $context->id
                && (int) $asset->context_id === (int) $context->id,
            404,
        );

        $this->authorizeLinkedAsset($request, $content, $asset);
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

        $this->authorizeLinkedAsset($request, $content, $asset);
    }

    private function authorizeLinkedAsset(Request $request, SpaceContent $content, Asset $asset): void
    {
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
