<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\CommitmentEvent;
use App\Models\Context;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves images, video, audio and files attached to a Deal's work (task media, reports, work-log entries) to people who can see that Deal's Context. */
class DealAssetController extends Controller
{
    public function show(Request $request, Asset $asset): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = Context::query()->findOrFail($asset->context_id);
        Gate::forUser($user)->authorize('view', $context);

        $attached = DB::table('fulfillment_assets')->where('asset_id', $asset->id)->exists()
            || CommitmentEvent::query()->whereJsonContains('payload->asset_ids', $asset->id)->exists();
        abort_unless($attached && Storage::disk($asset->disk)->exists($asset->storage_key), 404);

        return Storage::disk($asset->disk)->response($asset->storage_key, $asset->original_filename, [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
