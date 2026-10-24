<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\ActorProfileIntent;
use App\Models\IntentVersion;
use App\Support\Market\IntentPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntentPresentationController extends Controller
{
    public function show(Request $request, ActorProfileIntent $intent, IntentPresentation $presentation): Response
    {
        $data = $presentation->published($intent, $request->user());

        return response()->view('intents.show', ['intent' => $intent, 'presentation' => $data])
            ->header('Cache-Control', 'private, no-store');
    }

    /** Historical versions are for the people who manage the post, not its audience. */
    public function version(Request $request, ActorProfileIntent $intent, IntentVersion $version, IntentPresentation $presentation): Response
    {
        abort_unless($version->actor_profile_intent_id === $intent->id, 404);
        $data = $presentation->build($version->snapshot, $request->user(), true, $intent);

        return response()->view('intents.show', ['intent' => $intent, 'presentation' => $data, 'version' => $version])
            ->header('Cache-Control', 'private, no-store');
    }
}
