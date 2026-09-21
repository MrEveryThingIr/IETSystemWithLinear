<?php

namespace App\Actions\Profile;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateActorProfileIntent
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(User $user, ActorProfileIntent $intent, array $input): ActorProfileIntent
    {
        Gate::forUser($user)->authorize('update', $intent);
        abort_if($intent->status === ProfileIntentStatus::Closed, 409, 'Closed Profile declarations cannot be edited.');

        $data = app(NormalizeProfileIntentData::class)->execute($user, $input);

        return DB::transaction(function () use ($user, $intent, $data): ActorProfileIntent {
            $locked = ActorProfileIntent::query()
                ->with(['profile', 'concept'])
                ->lockForUpdate()
                ->findOrFail($intent->id);

            Gate::forUser($user)->authorize('update', $locked);
            abort_if($locked->status === ProfileIntentStatus::Closed, 409, 'Closed Profile declarations cannot be edited.');

            $locked->fill($data);
            $locked->save();

            app(SyncProfileIntentConceptAssertion::class)->execute(
                $user,
                $locked->profile,
                $locked->concept,
                $locked->kind,
            );

            return $locked->refresh()->load('concept.labels');
        }, 3);
    }
}
