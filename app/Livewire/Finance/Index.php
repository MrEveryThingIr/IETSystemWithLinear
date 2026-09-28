<?php

namespace App\Livewire\Finance;

use App\Actions\Exchange\PublishIetValuation;
use App\Actions\Exchange\ReviewIetExchangeRequest;
use App\Actions\Exchange\SubmitIetExchangeRequest;
use App\IetExchangeDirection;
use App\IetExchangeStatus;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\IetExchangeRequest;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetValueMath;
use App\Support\IetValuation;
use App\Support\IetWallet;
use App\Support\MoneyAmount;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Finance')]
class Index extends Component
{
    public string $exchangeDirection = 'deposit';

    public string $exchangeUsdAmount = '';

    public string $externalReference = '';

    public string $exchangeNote = '';

    public string $valuationXPercent = '';

    public string $valuationNote = '';

    /** @var array<int, string> */
    public array $reviewNotes = [];

    public function mount(IetValuation $valuation): void
    {
        $this->valuationXPercent = IetValueMath::xPercent(
            $valuation->current()->usd_pico_per_iet,
        );
    }

    public function submitExchange(SubmitIetExchangeRequest $submit): void
    {
        $data = $this->validate([
            'exchangeDirection' => ['required', 'in:deposit,cashout'],
            'exchangeUsdAmount' => ['required', 'string', 'max:40'],
            'externalReference' => ['nullable', 'string', 'max:255'],
            'exchangeNote' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $usdMinor = MoneyAmount::parse($data['exchangeUsdAmount'], 2);
        } catch (InvalidArgumentException) {
            $this->addError('exchangeUsdAmount', __('finance_baseline.validation.amount'));

            return;
        }

        $submit->execute(
            $this->user(),
            IetExchangeDirection::from($data['exchangeDirection']),
            $usdMinor,
            trim($data['externalReference']) !== '' ? $data['externalReference'] : null,
            trim($data['exchangeNote']) !== '' ? $data['exchangeNote'] : null,
        );

        $this->reset(['exchangeUsdAmount', 'externalReference', 'exchangeNote']);
        session()->flash('status', __('finance_baseline.messages.exchange_submitted'));
    }

    public function reviewExchange(
        int $requestId,
        bool $confirm,
        ReviewIetExchangeRequest $review,
    ): void {
        $user = $this->user();
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $request = IetExchangeRequest::query()
            ->where('status', IetExchangeStatus::Pending->value)
            ->findOrFail($requestId);

        $note = trim($this->reviewNotes[$requestId] ?? '');

        if ($confirm) {
            $review->confirm($request, $user, $note !== '' ? $note : null);
        } else {
            $review->reject($request, $user, $note !== '' ? $note : null);
        }

        unset($this->reviewNotes[$requestId]);
        session()->flash('status', $confirm
            ? __('finance_baseline.messages.exchange_confirmed')
            : __('finance_baseline.messages.exchange_rejected'));
    }

    public function publishValuation(PublishIetValuation $publish): void
    {
        $data = $this->validate([
            'valuationXPercent' => ['required', 'string', 'max:40'],
            'valuationNote' => ['nullable', 'string', 'max:5000'],
        ]);

        $publish->execute(
            $this->user(),
            $data['valuationXPercent'],
            trim($data['valuationNote']) !== '' ? $data['valuationNote'] : null,
        );

        $this->valuationNote = '';
        session()->flash('status', __('finance_baseline.messages.valuation_published'));
    }

    public function render(IetValuation $valuation, IetWallet $wallet): View
    {
        $user = $this->user();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $snapshot = $valuation->current();
        $availableIet = $wallet->availableMinor($user);
        $reservedIet = $wallet->reservedMinor($user);
        $canManageExchange = $user->hasPlatformCapability(PlatformCapability::ManageExchange);

        $myExchangeRequests = IetExchangeRequest::query()
            ->where('user_id', $user->id)
            ->with(['valuation', 'reviewedBy.user'])
            ->latest('id')
            ->limit(50)
            ->get();

        $pendingExchangeRequests = $canManageExchange
            ? IetExchangeRequest::query()
                ->where('status', IetExchangeStatus::Pending->value)
                ->with(['user', 'valuation'])
                ->oldest('id')
                ->limit(100)
                ->get()
            : collect();

        $obligations = FinancialObligation::query()
            ->where(function ($query) use ($actor): void {
                $query
                    ->where('debtor_actor_id', $actor->id)
                    ->orWhere('creditor_actor_id', $actor->id);
            })
            ->with(['debtor.user', 'creditor.user', 'monetaryUnit', 'settlements'])
            ->latest('id')
            ->limit(100)
            ->get();

        $obligationQuotes = [];
        foreach ($obligations as $obligation) {
            if ($obligation->monetaryUnit->code !== 'USD' || $obligation->availableToSettleMinor() <= 0) {
                continue;
            }

            $obligationQuotes[$obligation->id] = IetValueMath::ietMinorForUsdMinor(
                $obligation->availableToSettleMinor(),
                $snapshot->usd_pico_per_iet,
            );
        }

        $exchangePreview = null;
        if ($this->exchangeUsdAmount !== '') {
            try {
                $usdMinor = MoneyAmount::parse($this->exchangeUsdAmount, 2);
                if ($usdMinor > 0) {
                    $exchangePreview = IetValueMath::ietMinorForUsdMinor(
                        $usdMinor,
                        $snapshot->usd_pico_per_iet,
                    );
                }
            } catch (InvalidArgumentException) {
                $exchangePreview = null;
            }
        }

        return view('livewire.finance.index', compact(
            'snapshot',
            'availableIet',
            'reservedIet',
            'canManageExchange',
            'myExchangeRequests',
            'pendingExchangeRequests',
            'obligations',
            'obligationQuotes',
            'exchangePreview',
        ));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
