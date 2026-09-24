<?php

namespace App\Actions\Contracts;

use App\ContractEventType;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class ActivateContractVersion
{
    public function execute(ContractVersion $version, ?Actor $actor = null): ContractVersion
    {
        return DB::transaction(function () use ($version, $actor): ContractVersion {
            $locked = ContractVersion::query()
                ->with('contract')
                ->lockForUpdate()
                ->findOrFail($version->id);

            if ($locked->status === ContractVersionStatus::Active) {
                return $locked;
            }

            abort_unless(
                $locked->status === ContractVersionStatus::Accepted,
                422,
                'Only an accepted ContractVersion may activate.',
            );

            $now = CarbonImmutable::now();

            if ($locked->effective_from?->isAfter($now)) {
                return $locked;
            }

            $contract = Contract::query()->lockForUpdate()->findOrFail($locked->contract_id);

            $otherActive = ContractVersion::query()
                ->where('contract_id', $contract->id)
                ->where('status', ContractVersionStatus::Active->value)
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->get();

            if ($locked->supersedes_version_id === null) {
                if ($otherActive->isNotEmpty()) {
                    throw new LogicException('Initial ContractVersion cannot activate while another version is active.');
                }
            } else {
                $previous = ContractVersion::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->supersedes_version_id);

                abort_unless(
                    (int) $previous->contract_id === (int) $contract->id
                    && $previous->status === ContractVersionStatus::Active,
                    422,
                    'The ContractVersion selected for supersession is no longer active.',
                );

                abort_if(
                    $otherActive->contains(fn (ContractVersion $candidate): bool => (int) $candidate->id !== (int) $previous->id),
                    409,
                    'Contract has conflicting active versions.',
                );

                $previous->supersede($locked->effective_from, $now);

                ContractEvent::query()->create([
                    'contract_id' => $contract->id,
                    'contract_version_id' => $previous->id,
                    'actor_id' => $actor?->id,
                    'event_type' => ContractEventType::VersionSuperseded,
                    'payload' => [
                        'superseded_by_version_uuid' => $locked->uuid,
                        'effective_until' => $locked->effective_from?->toISOString(),
                    ],
                ]);
            }

            $locked->activate($now);
            $contract->activate();

            ContractEvent::query()->create([
                'contract_id' => $contract->id,
                'contract_version_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => ContractEventType::VersionActivated,
                'payload' => [
                    'effective_from' => $locked->effective_from?->toISOString(),
                    'version' => $locked->version,
                ],
            ]);

            return $locked->fresh([
                'contract',
                'termsRevision.content',
                'parties.actor.user',
                'parties.acceptance',
            ]);
        }, attempts: 3);
    }
}
