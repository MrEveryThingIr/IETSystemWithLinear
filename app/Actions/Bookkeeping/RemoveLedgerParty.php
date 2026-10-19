<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\PartyStatus;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Takes someone out of a book (or lets a person leave). Their entries stay
 * exactly as recorded; they just stop seeing the book.
 */
class RemoveLedgerParty
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(LedgerParty $party, User $user): LedgerParty
    {
        $actor = $this->recorder->actor($user);
        $leavingThemselves = (int) $party->actor_id === (int) $actor->id;

        if (! $leavingThemselves) {
            Gate::forUser($user)->authorize('manage', $party->ledger);
        }
        abort_if($party->role === PartyRole::Owner, 422, 'The owner stays with the book.');

        return DB::transaction(function () use ($party, $actor): LedgerParty {
            $locked = LedgerParty::query()->lockForUpdate()->findOrFail($party->id);
            if ($locked->status === PartyStatus::Left) {
                return $locked;
            }

            $locked->forceFill(['status' => PartyStatus::Left, 'left_at' => now()])->save();
            $this->recorder->audit($locked->ledger, $actor, 'party.left', before: ['party' => $locked->uuid, 'role' => $locked->role->value]);

            return $locked->refresh();
        }, 3);
    }
}
