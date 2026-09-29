<?php

namespace App\Livewire\Exchange;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Exchange\CreateIetExchangeRequest;
use App\Actions\Exchange\PublishIetValuationQuote;
use App\Actions\Exchange\PublishManualMarketQuote;
use App\Actions\Exchange\RegisterEconomicInstrument;
use App\Actions\Exchange\ReviewIetExchangeRequest;
use App\EconomicInstrumentKind;
use App\IetExchangeDirection;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Context;
use App\Models\EconomicInstrument;
use App\Models\IetExchangeRequest;
use App\Models\IetValuationQuote;
use App\Models\Ledger;
use App\Models\MarketQuote;
use App\Models\MonetaryUnit;
use App\Models\User;
use App\PlatformCapability;
use App\Support\AccountingSummary;
use App\Support\IetPricing;
use App\Support\IetTreasuryMetrics;
use App\Support\IetValuationMetrics;
use App\Support\MoneyAmount;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('IET Exchange')]
class Index extends Component
{
    public string $direction = 'deposit';

    public string $usdAmount = '';

    public string $externalReference = '';

    public string $note = '';

    /** @var array<int, string> */
    public array $reviewNotes = [];

    public string $newUsdPerIet = '';

    public string $valuationRationale = '';

    public string $instrumentCode = '';

    public string $instrumentName = '';

    public string $instrumentKind = 'crypto_asset';

    public string $marketBaseUuid = '';

    public string $marketQuoteUuid = '';

    public string $marketPrice = '';

    public string $marketRationale = '';

    public string $marketSourceReference = '';

    public function mount(EnsurePersonalContext $contexts, IetPricing $pricing): void
    {
        $contexts->execute($this->user());
        $this->newUsdPerIet = (string) $pricing->currentQuote()->usd_per_iet;

        $this->marketBaseUuid = (string) EconomicInstrument::query()->where('code', 'IET')->value('uuid');
        $this->marketQuoteUuid = (string) EconomicInstrument::query()->where('code', 'USD')->value('uuid');
    }

    public function submit(CreateIetExchangeRequest $create): void
    {
        $data = $this->validate([
            'direction' => ['required', 'in:deposit,cashout'],
            'usdAmount' => ['required', 'string', 'max:40'],
            'externalReference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $usdMinor = MoneyAmount::parse($data['usdAmount'], 2);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'usdAmount' => __('exchange.validation.usd_amount'),
            ]);
        }

        abort_if($usdMinor <= 0, 422, __('exchange.validation.usd_amount'));

        $create->execute(
            $this->user(),
            IetExchangeDirection::from($data['direction']),
            $usdMinor,
            $data['externalReference'] !== '' ? $data['externalReference'] : null,
            $data['note'] !== '' ? $data['note'] : null,
        );

        $this->reset('usdAmount', 'externalReference', 'note');
        session()->flash('status', __('exchange.messages.requested'));
    }

    public function confirmRequest(int $id, ReviewIetExchangeRequest $review): void
    {
        $request = IetExchangeRequest::query()->findOrFail($id);
        $review->confirm($request, $this->user());
        unset($this->reviewNotes[$id]);

        session()->flash('status', __('exchange.messages.confirmed'));
    }

    public function rejectRequest(int $id, ReviewIetExchangeRequest $review): void
    {
        $request = IetExchangeRequest::query()->findOrFail($id);
        $review->reject($request, $this->user(), $this->reviewNotes[$id] ?? null);
        unset($this->reviewNotes[$id]);

        session()->flash('status', __('exchange.messages.rejected'));
    }

    public function publishQuote(
        PublishIetValuationQuote $publish,
        IetValuationMetrics $metrics,
    ): void {
        $data = $this->validate([
            'newUsdPerIet' => ['required', 'string', 'max:32'],
            'valuationRationale' => ['required', 'string', 'max:4000'],
        ]);

        $publish->execute(
            $this->user(),
            $data['newUsdPerIet'],
            $data['valuationRationale'],
            $metrics->snapshot(),
        );

        $this->valuationRationale = '';
        session()->flash('status', __('exchange.messages.quote_published'));
    }


    public function registerInstrument(RegisterEconomicInstrument $register): void
    {
        $data = $this->validate([
            'instrumentCode' => ['required', 'string', 'max:48'],
            'instrumentName' => ['required', 'string', 'max:180'],
            'instrumentKind' => ['required', 'string'],
        ]);

        $kind = EconomicInstrumentKind::tryFrom($data['instrumentKind']);
        abort_unless($kind instanceof EconomicInstrumentKind, 422, 'Unsupported economic instrument kind.');

        $instrument = $register->execute(
            $this->user(),
            $data['instrumentCode'],
            $data['instrumentName'],
            $kind,
        );

        $this->reset('instrumentCode', 'instrumentName');
        $this->marketBaseUuid = $instrument->uuid;

        session()->flash('status', __('exchange.messages.instrument_registered'));
    }

    public function publishMarketQuote(PublishManualMarketQuote $publish): void
    {
        $data = $this->validate([
            'marketBaseUuid' => ['required', 'uuid'],
            'marketQuoteUuid' => ['required', 'uuid', 'different:marketBaseUuid'],
            'marketPrice' => ['required', 'string', 'max:60'],
            'marketRationale' => ['required', 'string', 'max:4000'],
            'marketSourceReference' => ['nullable', 'string', 'max:255'],
        ]);

        $publish->execute(
            $this->user(),
            $data['marketBaseUuid'],
            $data['marketQuoteUuid'],
            $data['marketPrice'],
            $data['marketRationale'],
            $data['marketSourceReference'] !== '' ? $data['marketSourceReference'] : null,
        );

        $this->reset('marketPrice', 'marketRationale', 'marketSourceReference');

        session()->flash('status', __('exchange.messages.market_quote_published'));
    }

    public function render(
        IetPricing $pricing,
        AccountingSummary $summary,
        IetValuationMetrics $metrics,
        IetTreasuryMetrics $treasury,
    ): View {
        $user = $this->user();
        $quote = $pricing->currentQuote();
        $context = $this->personalContext();
        $ledger = $this->ietLedger($context);
        $wallet = $ledger?->accounts()->where('system_key', 'cash')->first();
        $balance = $wallet instanceof Account ? $summary->accountBalanceMinor($wallet) : 0;

        $ownRequests = IetExchangeRequest::query()
            ->with('valuationQuote')
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(50)
            ->get();

        $canManage = $user->hasPlatformCapability(PlatformCapability::ManageExchange);

        $pendingReview = $canManage
            ? IetExchangeRequest::query()
                ->with(['user', 'valuationQuote'])
                ->where('status', 'pending')
                ->oldest('id')
                ->limit(100)
                ->get()
            : collect();

        $quotes = IetValuationQuote::query()
            ->with('publisher')
            ->latest('effective_at')
            ->latest('id')
            ->limit(20)
            ->get();

        $instruments = EconomicInstrument::query()
            ->orderByDesc('active')
            ->orderBy('code')
            ->get();

        $marketQuotes = MarketQuote::query()
            ->with(['baseInstrument', 'quoteInstrument', 'source'])
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->latest('id')
            ->limit(250)
            ->get()
            ->unique(fn (MarketQuote $marketQuote): string => $marketQuote->base_instrument_id.':'.$marketQuote->quote_instrument_id)
            ->take(100)
            ->values();

        return view('livewire.exchange.index', [
            'quote' => $quote,
            'quotePercent' => $pricing->percentOfUsd($quote),
            'quotePercents' => $quotes->mapWithKeys(
                fn (IetValuationQuote $historyQuote): array => [
                    $historyQuote->id => $pricing->percentOfUsd($historyQuote),
                ],
            ),
            'walletBalance' => $balance,
            'ownRequests' => $ownRequests,
            'canManageExchange' => $canManage,
            'pendingReview' => $pendingReview,
            'quotes' => $quotes,
            'valuationSignals' => $metrics->snapshot(),
            'instruments' => $instruments,
            'instrumentKinds' => EconomicInstrumentKind::cases(),
            'marketQuotes' => $marketQuotes,
            'treasuryMetrics' => $canManage ? $treasury->snapshot($user) : null,
        ]);
    }

    private function ietLedger(Context $context): ?Ledger
    {
        $unit = MonetaryUnit::query()->where('code', 'IET')->first();

        if (! $unit instanceof MonetaryUnit) {
            return null;
        }

        return $context->ledgers()
            ->where('monetary_unit_id', $unit->id)
            ->where('key', 'main')
            ->first();
    }

    private function personalContext(): Context
    {
        $actor = $this->user()->actor;
        abort_unless($actor instanceof Actor, 403);

        return Context::query()
            ->whereHas('personalBinding', fn ($query) => $query->where('actor_id', $actor->id))
            ->firstOrFail();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
