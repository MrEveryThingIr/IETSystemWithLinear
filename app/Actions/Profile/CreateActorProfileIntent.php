<?php

namespace App\Actions\Profile;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateActorProfileIntent
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(
        User $user,
        ActorProfile $profile,
        ProfileIntentKind $kind,
        string $conceptLabel,
        array $input,
    ): ActorProfileIntent {
        Gate::forUser($user)->authorize('update', $profile);

        $concept = app(ResolveActorProfileConcept::class)->execute($user, $profile, $conceptLabel);
        $data = app(NormalizeProfileIntentData::class)->execute($user, $input);

        return DB::transaction(function () use ($user, $profile, $kind, $concept, $data): ActorProfileIntent {
            $lockedProfile = ActorProfile::query()->lockForUpdate()->findOrFail($profile->id);
            Gate::forUser($user)->authorize('update', $lockedProfile);

            $actor = Actor::query()->findOrFail($lockedProfile->actor_id);
            $intent = new ActorProfileIntent;
            $intent->fill($data);
            $intent->profile()->associate($lockedProfile);
            $intent->concept()->associate($concept);
            $intent->creator()->associate($actor);
            $intent->kind = $kind;
            $intent->metadata = [];
            $intent->save();

            app(SyncProfileIntentConceptAssertion::class)->execute(
                $user,
                $lockedProfile,
                $concept,
                $kind,
            );

            return $intent->load('concept.labels');
        }, 3);
    }
}
