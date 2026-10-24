<?php

namespace App\Actions\Market;

use App\Actions\Profile\NormalizeProfileIntentData;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Support\Market\NormalizeIntentDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveIntentWorkingCopy
{
    /** @param array<string, mixed> $core @param list<array<string, mixed>> $details */
    public function execute(User $user, ActorProfileIntent $intent, array $core, array $details): ActorProfileIntent
    {
        Gate::forUser($user)->authorize('update', $intent);
        $data = app(NormalizeProfileIntentData::class)->execute($user, $core);
        $normalized = app(NormalizeIntentDetails::class)->execute($user, $details);

        return DB::transaction(function () use ($user, $intent, $data, $normalized): ActorProfileIntent {
            $locked = ActorProfileIntent::query()->lockForUpdate()->findOrFail($intent->id);
            Gate::forUser($user)->authorize('update', $locked);
            abort_if($locked->status === ProfileIntentStatus::Closed, 409);
            // The published-facing columns and indexes stay unchanged until publication.
            // Pending work is part of this same canonical record, never a parallel Need/Offer.
            $locked->forceFill(['working_copy' => ['core' => $data, 'details' => $normalized]])->save();

            return $locked->fresh();
        }, 3);
    }
}
