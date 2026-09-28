<?php

namespace App\Livewire\Financial;

use App\Actions\Financial\ProposeIetSettlement;
use App\Actions\Financial\RespondToSettlement;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\Settlement;
use App\Models\User;
use App\SettlementStatus;
use App\Support\IetValueMath;
use App\Support\IetValuation;
use App\Support\IetWallet;
use App\Support\MoneyAmount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('IET settlement')]
class BaselineShow extends Component
{
    public FinancialObligation $obligation;

    public string $settlementAmount = '';

    public string $settlementNote = '';

    /** @var array<int, string> */
    public array $rejectionNotes = [];

    public function mount(FinancialObligation $obligation): void
    {
        Gate::forUser($this->user())->authorize('view', $obligation);
        $this->obligation = $obligation;
        $this->refreshObligation();

        $this->settlementAmount = MoneyAmount::format(
            $this->obligation->availableToSettleMinor(),
            $this->obligation->monetaryUnit->exponent,
        );
    }

    public function propose(ProposeIetSettlement $propose): void
    {
        $this->refreshObligation();

        if ($this->obligation->monetaryUnit->code !== 'USD') {
            $this->addError('settlementAmount', __('finance_baseline.settlement.non_usd'));

            return;
        }

        try {
            $amountMinor = MoneyAmount::parse($this->settlementAmount, 2);
        } catch (InvalidArgumentException) {
            $this->addError('settlementAmount', __('finance_baseline.validation.amount'));

            return;
        }

        $propose->execute(
            $this->obligation,
            $this->user(),
            $amountMinor,
            trim($this->settlementNote) !== '' ? $this->settlementNote : null,
        );

        $this->settlementNote = '';
        $this->refreshObligation();
        $this->settlementAmount = MoneyAmount::format(
            $this->obligation->availableToSettleMinor(),
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
        if ($note === '') {
            $this->addError('rejectionNotes.'.$settlementId, __('finance_baseline.settlement.rejection_note'));

            return;
        }

        $respond->reject($settlement, $this->user(), $note);
        unset($this->rejectionNotes[$settlementId]);

        $this->refreshObligation();
        session()->flash('status', __('financial.messages.settlement_rejected'));
    }

    public function render(IetValuation $valuation, IetWallet $wallet): View
    {
        $this->refreshObligation();

        $user = $this->user();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $snapshot = $valuation->current();
        $availableIet = $wallet->availableMinor($user);
        $isDebtor = (int) $actor->id === (int) $this->obligation->debtor_actor_id;

        $quoteIet = null;
        if ($this->obligation->monetaryUnit->code === 'USD') {
            try {
                $amountMinor = MoneyAmount::parse($this->settlementAmount, 2);
                if ($amountMinor > 0) {
                    $quoteIet = IetValueMath::ietMinorForUsdMinor(
                        $amountMinor,
                        $snapshot->usd_pico_per_iet,
                    );
                }
            } catch (InvalidArgumentException) {
                $quoteIet = null;
            }
        }

        return view('livewire.financial.baseline-show', [
            'actor' => $actor,
            'snapshot' => $snapshot,
            'availableIet' => $availableIet,
            'quoteIet' => $quoteIet,
            'isDebtor' => $isDebtor,
            'canPropose' => $isDebtor
                && $this->obligation->monetaryUnit->code === 'USD'
                && $this->obligation->availableToSettleMinor() > 0
                && Gate::forUser($user)->allows('proposeSettlement', $this->obligation),
        ]);
    }

    private function refreshObligation(): void
    {
        $obligation = FinancialObligation::query()
            ->with([
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'settlements.proposedBy.user',
                'settlements.confirmedBy.user',
                'settlements.rejectedBy.user',
                'settlements.ietValuation',
            ])
            ->findOrFail($this->obligation->id);

        Gate::forUser($this->user())->authorize('view', $obligation);
        $this->obligation = $obligation;
    }

    private function settlement(int $id): Settlement
    {
        return Settlement::query()
            ->where('financial_obligation_id', $this->obligation->id)
            ->whereNotNull('iet_amount_minor')
            ->findOrFail($id);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
