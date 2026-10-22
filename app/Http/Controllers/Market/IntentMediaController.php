<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\ActorProfileIntent;
use App\Models\ActorProfileIntentMedia;
use App\Models\BusinessListingMedia;
use App\Support\AssetHttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Serves a Need/Offer's photo only to people who may see the Need/Offer. */
class IntentMediaController extends Controller
{
    public function show(Request $request, ActorProfileIntent $intent, ActorProfileIntentMedia $media): Response
    {
        abort_unless((int) $media->actor_profile_intent_id === (int) $intent->id, 404);
        Gate::forUser($request->user())->authorize('view', $intent);

        return $this->private(AssetHttpResponse::inline($media->asset()->firstOrFail()));
    }

    /** A business offer shows the cover of the exact catalog version it was published from. */
    public function listingCover(Request $request, ActorProfileIntent $intent): Response
    {
        Gate::forUser($request->user())->authorize('view', $intent);
        abort_if($intent->business_listing_version_id === null, 404);
        $media = BusinessListingMedia::query()
            ->where('business_listing_version_id', $intent->business_listing_version_id)
            ->where('visibility', 'public')
            ->orderByRaw("case when role = 'cover' then 0 else 1 end")
            ->orderBy('position')
            ->with('asset')
            ->first();
        abort_unless($media instanceof BusinessListingMedia && $media->asset !== null, 404);

        return $this->private(AssetHttpResponse::inline($media->asset));
    }

    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }
}
