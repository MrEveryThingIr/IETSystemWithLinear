<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\ActorProfileIntent;
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
}
