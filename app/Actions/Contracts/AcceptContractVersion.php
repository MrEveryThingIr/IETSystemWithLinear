<?php

namespace App\Actions\Contracts;

use App\ContractEventType;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractEvent;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcceptContractVersion
{
    public function __construct(private readonly FinalizeContractVersionAcceptance $finalize) {}

    public function execute(ContractVersion $version, User $user): ContractAcceptance
    {
        $current = $this->currentUser($user);
        $contract = $version->contract()->firstOrFail();

        Gate::forUser($current)->authorize('accept', [$contract, $version]);

        $acceptance = DB::transaction(function () use ($version, $current): ContractAcceptance {
            $locked = ContractVersion::query()
                ->lockForUpdate()
                ->findOrFail($version->id);

            $party = ContractVersionParty::query()
                ->where('contract_version_id', $locked->id)
                ->where('actor_id', $current->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                ContractAcceptance::query()
                    ->where('contract_version_party_id', $party->id)
                    ->exists(),
                422,
                'This party has already accepted this ContractVersion.',
            );

            $acceptance = ContractAcceptance::query()->create([
                'contract_version_party_id' => $party->id,
                'accepted_by_user_id' => $current->id,
                'accepted_at' => now(),
            ]);

            ContractEvent::query()->create([
                'contract_id' => $locked->contract_id,
                'contract_version_id' => $locked->id,
                'actor_id' => $current->actor->id,
                'event_type' => ContractEventType::PartyAccepted,
                'payload' => [
                    'contract_version_party_uuid' => $party->uuid,
                    'acceptance_uuid' => $acceptance->uuid,
                ],
            ]);

            return $acceptance->fresh(['party.contractVersion', 'acceptedByUser']);
        }, attempts: 3);

        $this->finalize->execute($acceptance->party->contractVersion, $current->actor);

        return $acceptance;
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
