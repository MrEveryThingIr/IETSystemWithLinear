<?php

namespace App\Http\Controllers;

use App\Models\ActorProfile;
use App\Models\ActorProfileImage;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActorProfileImageController extends Controller
{
    public function show(
        Request $request,
        ActorProfile $profile,
        ActorProfileImage $image,
    ): StreamedResponse {
        Gate::authorize('view', $profile);

        abort_unless((int) $image->actor_profile_id === (int) $profile->id, 404);

        $asset = $image->asset()->first();
        abort_unless($asset instanceof Asset, 404);
        abort_unless($asset->mediaKind() === 'image', 404);
        abort_unless($asset->isReadyForPublication(), 404);
        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);

        return Storage::disk($asset->disk)->response(
            $asset->storage_key,
            $asset->original_filename,
            [
                'Content-Type' => $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            ],
            'inline',
        );
    }
}
