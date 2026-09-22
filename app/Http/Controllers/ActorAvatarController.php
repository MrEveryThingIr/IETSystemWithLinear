<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\Asset;
use App\Policies\ActorProfilePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActorAvatarController extends Controller
{
    public function __invoke(
        Request $request,
        Actor $actor,
        ActorProfilePolicy $policy,
    ): Response|StreamedResponse {
        $actor->loadMissing(['user', 'profile.displayImage.asset']);
        $profile = $actor->profile;

        if ($profile instanceof ActorProfile && $policy->viewAvatar($request->user(), $profile)) {
            $asset = $profile->displayImage?->asset;

            if ($asset instanceof Asset
                && $asset->mediaKind() === 'image'
                && $asset->isReadyForPublication()
                && Storage::disk($asset->disk)->exists($asset->storage_key)) {
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

        return $this->placeholder($actor);
    }

    private function placeholder(Actor $actor): Response
    {
        $label = $actor->user?->username ?: '#'.$actor->id;
        $initial = mb_strtoupper(mb_substr($label, 0, 1));
        $safe = htmlspecialchars($initial, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128" role="img">
  <rect width="128" height="128" rx="28" fill="#e4e4e7"/>
  <text x="64" y="76" text-anchor="middle" font-family="sans-serif" font-size="52" font-weight="600" fill="#71717a">{$safe}</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'none'; sandbox",
        ]);
    }
}
