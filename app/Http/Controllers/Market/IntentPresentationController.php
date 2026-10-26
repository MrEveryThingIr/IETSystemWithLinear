<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\ActorProfileIntent;
use App\Models\IntentVersion;
use App\Models\Relationship;
use App\Models\User;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Illuminate\Support\Collection;
use App\Support\Market\IntentPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntentPresentationController extends Controller
{
    public function show(Request $request, ActorProfileIntent $intent, IntentPresentation $presentation): Response
    {
        $data = $presentation->published($intent, $request->user());

        return response()->view('intents.show', ['intent' => $intent, 'presentation' => $data, 'deals' => $this->deals($request->user(), $intent)])
            ->header('Cache-Control', 'private, no-store');
    }

    /** The conversations this person has about the post: the owner's inbox for it, a responder's own thread. */
    private function deals(?User $user, ActorProfileIntent $intent): Collection
    {
        if (! $user instanceof User || ! $user->actor) {
            return collect();
        }

        return Relationship::query()
            ->where('originating_intent_id', $intent->id)
            ->whereIn('status', [RelationshipStatus::Proposed->value, RelationshipStatus::Active->value])
            ->whereHas('participants', fn ($q) => $q->where('actor_id', $user->actor->id))
            ->with('participants')
            ->latest('id')
            ->get()
            ->map(fn (Relationship $deal): array => [
                'deal' => $deal,
                'waiting' => $deal->status === RelationshipStatus::Proposed && $deal->participants->contains(fn ($p) => (int) $p->actor_id === (int) $user->actor->id && $p->status === RelationshipParticipantStatus::Invited),
            ]);
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
