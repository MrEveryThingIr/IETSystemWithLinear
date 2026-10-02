<?php

namespace App\Actions\Financial;

use App\Models\Actor;
use App\Models\ContractServiceTerm;
use App\Models\FinancialObligation;
use App\Models\Fulfillment;
use App\Models\User;
use App\Support\ServiceCompensation;

class RecognizeServiceFulfillmentFinancialObligation
{
    public function __construct(
        private readonly RecognizeFulfillmentFinancialObligation $recognize,
        private readonly ServiceCompensation $compensation,
        private readonly PostFinancialObligationAccounting $postAccounting,
    ) {}

    public function execute(Fulfillment $fulfillment, User $user): ?FinancialObligation
    {
        $fulfillment = Fulfillment::query()
            ->with([
                'commitment.serviceTerm.monetaryUnit',
                'commitment.contractVersion',
                'financialObligation',
            ])
            ->findOrFail($fulfillment->id);

        if ($fulfillment->financialObligation instanceof FinancialObligation) {
            return $fulfillment->financialObligation;
        }

        $terms = $fulfillment->commitment->serviceTerm;

        if (! $terms instanceof ContractServiceTerm || ! $terms->auto_recognize_obligation) {
            return null;
        }

        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User
            || ! $current->actor instanceof Actor
            || (int) $current->actor->id !== (int) $terms->employer_actor_id) {
            return null;
        }

        $amountMinor = $this->compensation->amountMinor($terms, $fulfillment->quantity);
        $workAt = $fulfillment->actual_end_at
            ?? $fulfillment->reviewed_at
            ?? $fulfillment->submitted_at;

        $obligation = $this->recognize->execute(
            $fulfillment,
            $current,
            $terms->monetaryUnit->code,
            $amountMinor,
            $this->compensation->dueAt($terms, $workAt),
            'Accepted '.$fulfillment->quantity.' '.$terms->unit
                .' × '.$terms->unit_rate_minor.' minor units per '.$terms->unit
                .' under ContractVersion '.$fulfillment->commitment->contractVersion->version.'.',
        );

        if ($terms->monetaryUnit->code === 'IET') {
            $obligation->loadMissing(['debtor.user', 'creditor.user']);
            $this->postAccounting->execute($obligation, $obligation->debtor->user);
            $this->postAccounting->execute($obligation, $obligation->creditor->user);
        }

        return $obligation;
    }
}
