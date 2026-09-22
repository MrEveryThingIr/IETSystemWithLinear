<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileDisclosureGrant;
use App\Models\User;
use App\Policies\ActorProfilePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActorProfileReferenceController extends Controller
{
    public function __invoke(
        Request $request,
        Actor $actor,
        ActorProfilePolicy $profilePolicy,
    ): RedirectResponse|View {
        $actor->loadMissing(['user', 'profile']);
        $profile = $actor->profile;
        abort_unless($profile instanceof ActorProfile, 404);

        $viewer = $request->user();

        if ($profilePolicy->view($viewer, $profile)) {
            return redirect()->route('profiles.show', $profile);
        }

        if ($viewer instanceof User && $viewer->hasVerifiedEmail()) {
            $viewer->loadMissing('actor');

            if ($viewer->status === 'active' && $viewer->actor?->status === 'active') {
                $grant = ActorProfileDisclosureGrant::query()
                    ->where('actor_profile_id', $profile->id)
                    ->where('grantee_actor_id', $viewer->actor->id)
                    ->whereNull('revoked_at')
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->latest('id')
                    ->first();

                if ($grant instanceof ActorProfileDisclosureGrant
                    && Gate::forUser($viewer)->allows('view', $grant)) {
                    return redirect()->route('profiles.shares.show', $grant);
                }

                return view('profile.reference', ['actor' => $actor]);
            }
        }

        return view('profile.reference', ['actor' => $actor]);
    }
}
