<?php

namespace App\Actions\Profile;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetActorProfileIntentStatus
{
    public function execute(
        User $user,
        ActorProfileIntent $intent,
        ProfileIntentStatus $status,
    ): ActorProfileIntent {
        Gate::forUser($user)->authorize('update', $intent);

        return DB::transaction(function () use ($user, $intent, $status): ActorProfileIntent {
            $locked = ActorProfileIntent::query()
                ->with(['profile', 'concept'])
                ->lockForUpdate()
                ->findOrFail($intent->id);

            Gate::forUser($user)->authorize('update', $locked);

            abort_if(
                $locked->status === ProfileIntentStatus::Closed && $status !== ProfileIntentStatus::Closed,
                409,
                'Closed Profile declarations are historical and cannot be reopened.',
            );

            $locked->applyStatus($status);

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
