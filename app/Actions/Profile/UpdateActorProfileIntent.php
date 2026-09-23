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

        $input += [
            'subject_kind' => $intent->subject_kind->value,
            'arrangement_kind' => $intent->arrangement_kind->value,
            'exchange_preference' => $intent->exchange_preference->value,
            'cash_min' => $intent->cash_min,
            'cash_max' => $intent->cash_max,
            'currency_code' => $intent->currency_code,
            'cash_basis' => $intent->cash_basis,
            'exchange_notes' => $intent->exchange_notes,
        ];

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
