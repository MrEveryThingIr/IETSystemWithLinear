<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\PartyStatus;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a person to a shared or project book: someone with an account (by
 * username) or a name-only person, such as a worker without an account.
 * Name-only people can be recorded but cannot confirm entries.
 */
class AddLedgerParty
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(
        Ledger $ledger,
        User $user,
        ?string $username,
        ?string $name,
        PartyRole $role = PartyRole::Member,
        ?string $contactHint = null,
    ): LedgerParty {
        Gate::forUser($user)->authorize('manage', $ledger);
        abort_if($role === PartyRole::Owner, 422, 'A book has one owner.');

        $username = $username !== null ? ltrim(Str::squish($username), '@') : '';
        $name = $name !== null ? Str::squish($name) : '';
        $contactHint = $contactHint !== null ? Str::squish($contactHint) : '';

        if ($username === '' && $name === '') {
            throw ValidationException::withMessages(['partyName' => __('bookkeeping.validation.party_required')]);
        }
        if (mb_strlen($name) > 120 || mb_strlen($contactHint) > 120) {
            throw ValidationException::withMessages(['partyName' => __('bookkeeping.validation.party_name_length')]);
        }

        $actor = $this->recorder->actor($user);

        return DB::transaction(function () use ($ledger, $actor, $username, $name, $role, $contactHint): LedgerParty {
            Ledger::query()->lockForUpdate()->findOrFail($ledger->id);

            if ($username !== '') {
                $member = User::query()->with('actor')->where('username', $username)->first();
                if (! $member instanceof User || ! $member->actor instanceof Actor || $member->status !== 'active') {
                    throw ValidationException::withMessages(['partyUsername' => __('bookkeeping.validation.user_not_found')]);
                }
                $partyActor = $member->actor;

                $existing = LedgerParty::query()->where('ledger_id', $ledger->id)->where('actor_id', $partyActor->id)->first();
                if ($existing instanceof LedgerParty) {
                    if ($existing->status === PartyStatus::Active) {
                        throw ValidationException::withMessages(['partyUsername' => __('bookkeeping.validation.already_in_book')]);
                    }
                    $existing->forceFill(['status' => PartyStatus::Active, 'role' => $role, 'left_at' => null])->save();
                    $this->recorder->audit($ledger, $actor, 'party.rejoined', after: ['party' => $existing->uuid, 'role' => $role->value]);
                    $this->recorder->notify([$existing], $ledger, 'added_to_book', ['book' => $ledger->name], $actor);

                    return $existing->refresh();
                }
            } else {
                // A name-only person: a new Actor without an account.
                $partyActor = new Actor;
                $partyActor->save();
            }

            $party = LedgerParty::query()->create([
                'ledger_id' => $ledger->id,
                'actor_id' => $partyActor->id,
                'display_name' => $name !== '' ? $name : null,
                'contact_hint' => $contactHint !== '' ? $contactHint : null,
                'role' => $role,
                'added_by_actor_id' => $actor->id,
            ]);

            $this->recorder->audit($ledger, $actor, 'party.added', after: ['party' => $party->uuid, 'role' => $role->value, 'has_account' => $username !== '']);
            $this->recorder->notify([$party], $ledger, 'added_to_book', ['book' => $ledger->name], $actor);

            return $party->refresh();
        }, 3);
    }
}
