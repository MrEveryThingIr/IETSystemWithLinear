<?php

namespace App\Actions\Commitments;

use App\CommitmentEventType;
use App\CommitmentKind;
use App\CommitmentStatus;
use App\ContractStatus;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\User;
use App\Support\QuantityAmount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCommitment
{
    public function execute(
        Contract $contract,
        User $user,
        Actor $obligor,
        Actor $beneficiary,
        CommitmentKind $kind,
        string $title,
        string|int $quantity,
        string $unit,
        ?string $description = null,
        ?CarbonInterface $dueStartAt = null,
        ?CarbonInterface $dueEndAt = null,
    ): Commitment {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('create', [Commitment::class, $contract]);

        $title = Str::squish($title);
        abort_if($title === '' || mb_strlen($title) > 180, 422, 'Commitment title must be between 1 and 180 characters.');

        $description = trim((string) $description);
        abort_if(mb_strlen($description) > 10000, 422, 'Commitment description may not exceed 10000 characters.');

        $unit = Str::squish($unit);
        abort_if($unit === '' || mb_strlen($unit) > 40, 422, 'Commitment unit must be between 1 and 40 characters.');

        try {
            $normalizedQuantity = QuantityAmount::positive($quantity);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        abort_if(
            $dueStartAt !== null
            && $dueEndAt !== null
            && $dueEndAt->lt($dueStartAt),
            422,
            'Commitment due end cannot precede its due start.',
        );

        return DB::transaction(function () use (
            $contract,
            $current,
            $obligor,
            $beneficiary,
            $kind,
            $title,
            $normalizedQuantity,
            $unit,
            $description,
            $dueStartAt,
            $dueEndAt,
        ): Commitment {
            $locked = Contract::query()
                ->with('versions')
                ->lockForUpdate()
                ->findOrFail($contract->id);

            abort_unless($locked->status === ContractStatus::Active, 422, 'Commitments require an active Contract.');

            $version = ContractVersion::query()
                ->where('contract_id', $locked->id)
                ->where('status', ContractVersionStatus::Active->value)
                ->lockForUpdate()
                ->first();

            abort_unless($version instanceof ContractVersion, 422, 'Active ContractVersion is missing.');

            $obligor = Actor::query()->with('user')->lockForUpdate()->findOrFail($obligor->id);
            $beneficiary = Actor::query()->with('user')->lockForUpdate()->findOrFail($beneficiary->id);

            foreach ([$obligor, $beneficiary] as $party) {
                abort_unless(
                    $party->status === 'active'
                    && $party->user instanceof User
                    && $party->user->status === 'active'
                    && $party->user->email_verified_at !== null
                    && ContractVersionParty::query()
                        ->where('contract_version_id', $version->id)
                        ->where('actor_id', $party->id)
                        ->exists(),
                    422,
                    'Commitment parties must be active verified parties of the exact active ContractVersion.',
                );
            }

            abort_if(
                (int) $obligor->id === (int) $beneficiary->id,
                422,
                'Commitment obligor and beneficiary must be different Actors.',
            );

            $creator = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $commitment = Commitment::query()->create([
                'contract_version_id' => $version->id,
                'created_by_actor_id' => $creator->id,
                'obligor_actor_id' => $obligor->id,
                'beneficiary_actor_id' => $beneficiary->id,
                'kind' => $kind,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'quantity' => $normalizedQuantity,
                'unit' => $unit,
                'due_start_at' => $dueStartAt?->utc(),
                'due_end_at' => $dueEndAt?->utc(),
                'status' => CommitmentStatus::Active,
            ]);

            CommitmentEvent::query()->create([
                'commitment_id' => $commitment->id,
                'actor_id' => $creator->id,
                'event_type' => CommitmentEventType::Created,
                'payload' => [
                    'contract_version_uuid' => $version->uuid,
                    'kind' => $kind->value,
                    'quantity' => $normalizedQuantity,
                    'unit' => $unit,
                    'obligor_actor_id' => $obligor->id,
                    'beneficiary_actor_id' => $beneficiary->id,
                ],
            ]);

            return $commitment->fresh([
                'contractVersion.contract.contextBinding.context',
                'creator.user',
                'obligor.user',
                'beneficiary.user',
                'events.actor.user',
            ]);
        }, attempts: 3);
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
