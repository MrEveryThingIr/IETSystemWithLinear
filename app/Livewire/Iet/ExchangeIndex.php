<?php

namespace App\Livewire\Iet;

use App\Actions\Iet\CancelIetExchangeRequest;
use App\Actions\Iet\CreateIetExchangeRequest;
use App\Actions\Iet\EnsureIetEconomy;
use App\Actions\Iet\EnsureIetWallet;
use App\Actions\Iet\PublishIetRateAdjustment;
use App\Actions\Iet\ReviewIetExchangeRequest;
use App\IetExchangeStatus;
use App\Models\IetExchangeRequest;
use App\Models\IetRateVersion;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetPricing;
use App\Support\IetRateMath;
use App\Support\IetWalletBalance;
use App\Support\MoneyAmount;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('IET & Exchange')]
class ExchangeIndex extends Component
{
    public string $direction = 'deposit';

    public string $amount = '';

    public string $note = '';

    public string $rateAdjustmentPercent = '';

    public string $rateReason = '';

    public string $rateCriteria = '';

    /** @var array<int,string> */
    public array $reviewReferences = [];

    /** @var array<int,string> */
    public array $rejectionNotes = [];

    public function createRequest(CreateIetExchangeRequest $create): void
    {
        $data = $this->validate([
            'direction' => ['required', 'in:deposit,cashout'],
            'amount' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            if ($data['direction'] === 'deposit') {
                $usdMinor = MoneyAmount::parse($data['amount'], 2);
                $create->deposit($this->user(), $usdMinor, $data['note']);
            } else {
                $ietMinor = MoneyAmount::parse($data['amount'], 0);
                $create->cashout($this->user(), $ietMinor, $data['note']);
            }
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'amount' => __('iet.validation.amount'),
            ]);
        }

        $this->reset('amount', 'note');
        session()->flash('status', __('iet.exchange.requested'));
    }

    public function cancel(int $id, CancelIetExchangeRequest $cancel): void
    {
        $request = IetExchangeRequest::query()
            ->where('user_id', $this->user()->id)
            ->findOrFail($id);

        $cancel->execute($request, $this->user());
        session()->flash('status', __('iet.exchange.cancelled'));
    }

    public function confirm(int $id, ReviewIetExchangeRequest $review): void
    {
        $request = IetExchangeRequest::query()->where('status', IetExchangeStatus::Pending->value)->findOrFail($id);
        $review->confirm($request, $this->user(), $this->reviewReferences[$id] ?? null);
        unset($this->reviewReferences[$id]);

        session()->flash('status', __('iet.exchange.confirmed'));
    }

    public function reject(int $id, ReviewIetExchangeRequest $review): void
    {
        $request = IetExchangeRequest::query()->where('status', IetExchangeStatus::Pending->value)->findOrFail($id);
        $note = trim($this->rejectionNotes[$id] ?? '');

        if ($note === '') {
            throw ValidationException::withMessages([
                "rejectionNotes.{$id}" => __('iet.exchange.rejection_required'),
            ]);
        }

        $review->reject($request, $this->user(), $note);
        unset($this->rejectionNotes[$id]);

        session()->flash('status', __('iet.exchange.rejected'));
    }

    public function publishRate(PublishIetRateAdjustment $publish): void
    {
        $data = $this->validate([
            'rateAdjustmentPercent' => ['required', 'string', 'max:20'],
            'rateReason' => ['required', 'string', 'max:500'],
            'rateCriteria' => ['nullable', 'string', 'max:2000'],
        ]);

        $ppm = $this->percentToPpm($data['rateAdjustmentPercent']);

        $publish->execute(
            $this->user(),
            $ppm,
            $data['rateReason'],
            $data['rateCriteria'] !== ''
                ? ['operator_note' => $data['rateCriteria']]
                : [],
        );

        $this->reset('rateAdjustmentPercent', 'rateReason', 'rateCriteria');
        session()->flash('status', __('iet.rate.published'));
    }

    public function render(
        EnsureIetEconomy $economy,
        EnsureIetWallet $wallet,
        IetWalletBalance $balances,
        IetPricing $pricing,
        IetRateMath $math,
    ): View {
        $user = $this->user();
        $state = $economy->execute();
        $wallet->execute($user);
        $currentRate = $economy->currentRate();
        $balance = $balances->forUser($user);
        $canReview = $user->hasPlatformCapability(PlatformCapability::ManagePlatformAccess);

        $quote = null;

        if (trim($this->amount) !== '') {
            try {
                $quote = $this->direction === 'deposit'
                    ? $pricing->quoteUsdMinor(MoneyAmount::parse($this->amount, 2))
                    : $pricing->quoteIetMinor(MoneyAmount::parse($this->amount, 0));
            } catch (\Throwable) {
                $quote = null;
            }
        }

        $myRequests = IetExchangeRequest::query()
            ->with(['rateVersion', 'fiatUnit', 'reviewer'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(30)
            ->get();

        $pendingRequests = $canReview
            ? IetExchangeRequest::query()
                ->with(['user', 'rateVersion', 'fiatUnit'])
                ->where('status', IetExchangeStatus::Pending->value)
                ->oldest('id')
                ->limit(50)
                ->get()
            : collect();

        $ietPerUsd = $pricing->quoteUsdMinor(100)['iet_minor'];

        $rateHistory = IetRateVersion::query()
            ->with('creator')
            ->latest('sequence')
            ->limit(12)
            ->get();

        return view('livewire.iet.exchange-index', [
            'balance' => $balance,
            'currentRate' => $currentRate,
            'usdPerIet' => $math->usdPerIet($currentRate),
            'percentOfDollar' => $math->percentOfDollar($currentRate),
            'quote' => $quote,
            'ietPerUsd' => $ietPerUsd,
            'myRequests' => $myRequests,
            'pendingRequests' => $pendingRequests,
            'rateHistory' => $rateHistory,
            'canReview' => $canReview,
        ]);
    }

    private function percentToPpm(string $value): int
    {
        $value = trim($value);

        if (! preg_match('/^-?\d{1,3}(?:\.\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([
                'rateAdjustmentPercent' => __('iet.validation.rate_adjustment'),
            ]);
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 4), 4, '0');

        $ppm = ((int) $whole * 10_000) + (int) $fraction;

        return $negative ? -$ppm : $ppm;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
