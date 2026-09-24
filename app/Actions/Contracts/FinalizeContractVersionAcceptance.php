<?php

namespace App\Actions\Contracts;

use App\ContractEventType;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\ContractAcceptance;
use App\Models\ContractEvent;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinalizeContractVersionAcceptance
{
    public function __construct(private readonly ActivateContractVersion $activate) {}

    public function execute(ContractVersion $version, ?Actor $actor = null): ContractVersion
    {
        $accepted = DB::transaction(function () use ($version, $actor): ContractVersion {
            $locked = ContractVersion::query()
                ->lockForUpdate()
                ->findOrFail($version->id);

            if ($locked->status !== ContractVersionStatus::Proposed) {
                return $locked;
            }

            $requiredPartyIds = ContractVersionParty::query()
                ->where('contract_version_id', $locked->id)
                ->where('required', true)
                ->pluck('id');

            abort_if($requiredPartyIds->isEmpty(), 422, 'ContractVersion requires at least one required party.');

            $acceptedPartyIds = ContractAcceptance::query()
                ->whereIn('contract_version_party_id', $requiredPartyIds)
                ->pluck('contract_version_party_id');

            if ($acceptedPartyIds->unique()->count() !== $requiredPartyIds->count()) {
                return $locked;
            }

            $now = CarbonImmutable::now();
            $locked->markAccepted($now);

            ContractEvent::query()->create([
                'contract_id' => $locked->contract_id,
                'contract_version_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => ContractEventType::VersionAccepted,
                'payload' => [
                    'accepted_at' => $now->toISOString(),
                    'version' => $locked->version,
                ],
            ]);

            return $locked->fresh();
        }, attempts: 3);

        if ($accepted->status === ContractVersionStatus::Accepted
            && ! $accepted->effective_from->isFuture()) {
            return $this->activate->execute($accepted, $actor);
        }

        return $accepted;
    }
}
