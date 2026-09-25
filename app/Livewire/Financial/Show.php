<?php

namespace App\Livewire\Financial;

use App\Actions\Financial\PostFinancialObligationAccounting;
use App\Actions\Financial\PostSettlementAccounting;
use App\Actions\Financial\ProposeSettlement;
use App\Actions\Financial\RespondToSettlement;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\JournalEntry;
use App\Models\Settlement;
use App\Models\User;
use App\Support\MoneyAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Financial obligation')]
class Show extends Component
{
    public FinancialObligation $obligation;

    public string $settlementAmount = '';

    public string $settlementPaidAt = '';

    public string $settlementMethod = '';

    public string $settlementReference = '';

    public string $settlementNote = '';

    /** @var array<int, string> */
    public array $rejectionNotes = [];

    public function mount(FinancialObligation $obligation): void
    {
        Gate::forUser($this->user())->authorize('view', $obligation);
        $this->obligation = $obligation;

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->settlementPaidAt = CarbonImmutable::now($timezone)->format('Y-m-d\TH:i');

        $this->refreshObligation();
        $this->settlementAmount = MoneyAmount::format(
            $this->obligation->outstandingMinor(),
            $this->obligation->monetaryUnit->exponent,
        );
    }

    public function postObligationAccounting(PostFinancialObligationAccounting $post): void
    {
        $post->execute($this->obligation, $this->user());
        $this->refreshObligation();

        session()->flash('status', __('financial.messages.obligation_accounting_posted'));
    }

    public function proposeSettlement(ProposeSettlement $propose): void
    {
        $this->refreshObligation();

        $amountMinor = $this->parseAmount($this->settlementAmount);
        $paidAt = $this->paidInstant();

        $propose->execute(
            $this->obligation,
            $this->user(),
            $amountMinor,
            $paidAt,
            $this->settlementMethod !== '' ? $this->settlementMethod : null,
            $this->settlementReference !== '' ? $this->settlementReference : null,
            $this->settlementNote !== '' ? $this->settlementNote : null,
        );

        $this->reset('settlementMethod', 'settlementReference', 'settlementNote');
        $this->refreshObligation();

        $this->settlementAmount = MoneyAmount::format(
            $this->obligation->outstandingMinor(),
            $this->obligation->monetaryUnit->exponent,
        );

        session()->flash('status', __('financial.messages.settlement_proposed'));
    }

    public function confirmSettlement(int $settlementId, RespondToSettlement $respond): void
    {
        $settlement = $this->settlement($settlementId);
        Gate::forUser($this->user())->authorize('respond', $settlement);

        $respond->confirm($settlement, $this->user());
        $this->refreshObligation();

        session()->flash('status', __('financial.messages.settlement_confirmed'));
    }

    public function rejectSettlement(int $settlementId, RespondToSettlement $respond): void
    {
        $settlement = $this->settlement($settlementId);
        Gate::forUser($this->user())->authorize('respond', $settlement);

        $note = trim($this->rejectionNotes[$settlementId] ?? '');
        abort_if($note === '', 422, 'Settlement rejection reason is required.');

        $respond->reject($settlement, $this->user(), $note);
        unset($this->rejectionNotes[$settlementId]);

        $this->refreshObligation();
        session()->flash('status', __('financial.messages.settlement_rejected'));
    }

    public function postSettlementAccounting(
        int $settlementId,
        PostSettlementAccounting $post,
    ): void {
        $settlement = $this->settlement($settlementId);
        Gate::forUser($this->user())->authorize('postAccounting', $settlement);

        $post->execute($settlement, $this->user());
        $this->refreshObligation();

        session()->flash('status', __('financial.messages.settlement_accounting_posted'));
    }

    public function render(): View
    {
        $this->refreshObligation();

        $user = $this->user();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $obligationAccountingPosted = JournalEntry::query()
            ->where('acting_user_id', $user->id)
            ->where('source_type', 'financial_obligation')
            ->where('source_uuid', $this->obligation->uuid)
            ->exists();

        $settlementAccounting = JournalEntry::query()
            ->where('acting_user_id', $user->id)
            ->where('source_type', 'settlement')
            ->whereIn('source_uuid', $this->obligation->settlements->pluck('uuid'))
            ->pluck('source_uuid')
            ->flip();

        return view('livewire.financial.show', [
            'actor' => $actor,
            'timezone' => TemporalPreferences::timezoneFor($user),
            'obligationAccountingPosted' => $obligationAccountingPosted,
            'settlementAccounting' => $settlementAccounting,
            'canPostObligationAccounting' => Gate::forUser($user)
                ->allows('postAccounting', $this->obligation),
            'canProposeSettlement' => Gate::forUser($user)
                ->allows('proposeSettlement', $this->obligation),
        ]);
    }

    private function refreshObligation(): void
    {
        $obligation = FinancialObligation::query()
            ->with([
                'fulfillment.commitment.contractVersion.contract.contextBinding.context',
                'fulfillment.review.reviewer.user',
                'fulfillment.dispute',
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'recognizedBy.user',
                'settlements.proposedBy.user',
                'settlements.confirmedBy.user',
                'settlements.rejectedBy.user',
                'events.actor.user',
                'events.journalEntry',
            ])
            ->findOrFail($this->obligation->id);

        Gate::forUser($this->user())->authorize('view', $obligation);
        $this->obligation = $obligation;
    }

    private function settlement(int $id): Settlement
    {
        return Settlement::query()
            ->where('financial_obligation_id', $this->obligation->id)
            ->findOrFail($id);
    }

    private function parseAmount(string $value): int
    {
        try {
            $amount = MoneyAmount::parse(
                $value,
                $this->obligation->monetaryUnit->exponent,
            );
        } catch (InvalidArgumentException) {
            $this->addError('settlementAmount', __('financial.validation.amount'));
            abort(422, 'Invalid Settlement amount.');
        }

        abort_if($amount <= 0, 422, 'Settlement amount must be positive.');

        return $amount;
    }

    private function paidInstant(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse(
                $this->settlementPaidAt,
                TemporalPreferences::timezoneFor($this->user()),
            )->utc();
        } catch (Throwable) {
            $this->addError('settlementPaidAt', __('validation.date', [
                'attribute' => __('financial.settlement.paid_at'),
            ]));
            abort(422, 'Invalid Settlement paid time.');
        }
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
