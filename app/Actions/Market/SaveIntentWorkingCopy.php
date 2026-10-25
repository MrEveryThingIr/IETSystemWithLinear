<?php

namespace App\Actions\Market;

use App\Actions\Profile\NormalizeProfileIntentData;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\Support\Market\NormalizeIntentDetails;
use App\Support\Parties\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveIntentWorkingCopy
{
    /** @param array<string, mixed> $core @param list<array<string, mixed>> $details */
    public function execute(User $user, ActorProfileIntent $intent, array $core, array $details, ?ProfileIntentKind $kind = null): ActorProfileIntent
    {
        Gate::forUser($user)->authorize('update', $intent);
        $data = app(NormalizeProfileIntentData::class)->execute($user, $core);
        $normalized = app(NormalizeIntentDetails::class)->execute($user, $details, need: ($kind ?? $intent->kind) === ProfileIntentKind::Need);

        return DB::transaction(function () use ($user, $intent, $data, $normalized, $kind): ActorProfileIntent {
            $locked = ActorProfileIntent::query()->lockForUpdate()->findOrFail($intent->id);
            Gate::forUser($user)->authorize('update', $locked);
            abort_if($locked->status === ProfileIntentStatus::Closed, 409);
            if (($data['visibility'] ?? '') === 'members' && $locked->publisher()->kind === Party::ACTOR) {
                throw ValidationException::withMessages(['core.visibility' => __('intents.errors.members_needs_organization')]);
            }
            if ($kind !== null && $locked->kind !== $kind) {
                abort_unless($locked->status === ProfileIntentStatus::Draft && $locked->published_version_id === null, 409);
                $locked->kind = $kind;
            }
            // The published-facing columns and indexes stay unchanged until publication.
            // Pending work is part of this same canonical record, never a parallel Need/Offer.
            $locked->forceFill(['working_copy' => ['core' => $data, 'details' => $normalized]])->save();

            return $locked->fresh();
        }, 3);
    }
}
