<?php

namespace App\Actions\Financial;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\FinancialObligationEventType;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use App\Models\Fulfillment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecognizeFulfillmentFinancialObligation
{
    public function __construct(private readonly EnsureMonetaryUnit $units) {}

    public function execute(
        Fulfillment $fulfillment,
        User $user,
        string $unitCode,
        int $amountMinor,
        ?CarbonInterface $dueAt = null,
        ?string $description = null,
    ): FinancialObligation {
        $current = $this->currentUser($user);

        abort_if($amountMinor <= 0, 422, 'Financial Obligation amount must be positive.');

        $description = trim((string) $description);
        abort_if(mb_strlen($description) > 500, 422, 'Financial Obligation description is too long.');

        return DB::transaction(function () use (
            $fulfillment,
            $current,
            $unitCode,
            $amountMinor,
            $dueAt,
            $description,
        ): FinancialObligation {
            $locked = Fulfillment::query()
                ->with([
                    'commitment.contractVersion.contract',
                    'commitment.obligor.user',
                    'commitment.beneficiary.user',
                    'review',
                    'dispute',
                ])
                ->lockForUpdate()
                ->findOrFail($fulfillment->id);

            abort_unless(
                $locked->status === FulfillmentStatus::Accepted,
                422,
                'Only currently accepted Fulfillment may create a Financial Obligation.',
            );
            abort_unless(
                $locked->review !== null,
                422,
                'Financial Obligation requires explicit Fulfillment review evidence.',
            );

            $debtor = $locked->commitment->beneficiary;
            $creditor = $locked->commitment->obligor;

            abort_unless(
                (int) $current->actor->id === (int) $debtor->id,
                403,
                'The Fulfillment beneficiary/debtor must explicitly recognize the Financial Obligation.',
            );
            abort_if(
                FinancialObligation::query()->where('fulfillment_id', $locked->id)->exists(),
                422,
                'This Fulfillment already has a Financial Obligation.',
            );

            $unit = $this->units->execute($unitCode);
            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $obligation = FinancialObligation::query()->create([
                'fulfillment_id' => $locked->id,
                'contract_version_id' => $locked->commitment->contract_version_id,
                'debtor_actor_id' => $debtor->id,
                'creditor_actor_id' => $creditor->id,
                'monetary_unit_id' => $unit->id,
                'amount_minor' => $amountMinor,
                'description' => $description !== '' ? $description : null,
                'due_at' => $dueAt?->utc(),
                'recognized_by_actor_id' => $actor->id,
                'recognized_at' => now(),
            ]);

            FinancialObligationEvent::query()->create([
                'financial_obligation_id' => $obligation->id,
                'settlement_id' => null,
                'actor_id' => $actor->id,
                'event_type' => FinancialObligationEventType::Recognized,
                'payload' => [
                    'fulfillment_uuid' => $locked->uuid,
                    'contract_version_uuid' => $locked->commitment->contractVersion->uuid,
                    'monetary_unit_code' => $unit->code,
                    'amount_minor' => $amountMinor,
                ],
            ]);

            return $obligation->fresh([
                'fulfillment.commitment.contractVersion.contract.contextBinding.context',
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'recognizedBy.user',
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
