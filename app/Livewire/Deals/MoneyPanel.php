<?php

namespace App\Livewire\Deals;

use App\Actions\Deals\ManageDealMoney;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Deals\DealMoney;
use App\Support\Deals\DealNote;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Money tab of a Deal. It owns no records: amounts come from the accepted Agreement and confirmed work, and every
 * fact is recorded in the Deal's shared Bookkeeping book through ManageDealMoney.
 */
class MoneyPanel extends Component
{
    use WithFileUploads;

    public Relationship $relationship;

    /** @var array<string, bool> item key => chosen for a request */
    public array $selected = [];

    /** @var array<string, string> item key => amount typed when the terms give none */
    public array $typed = [];

    /** @var array<string, string> obligation uuid => amount being reported */
    public array $pay = [];

    /** @var array<string, string> free text keyed by purpose and entry */
    public array $text = [];

    public $receipt = null;

    /** Advance, instalment or final payment: each is its own event, named in the payment's description. */
    public string $purpose = '';

    /** @var array<string, string> asking for money on account */
    public array $ask = ['amount' => '', 'note' => ''];

    /** @var array<string, string> paying on account */
    public array $account = ['amount' => '', 'ask' => '', 'purpose' => 'advance'];

    /** One token per form: submitting it twice records one payment. */
    public string $payToken = '';

    public function mount(Relationship $relationship): void
    {
        $this->relationship = $relationship;
        $this->payToken = (string) Str::uuid();
    }

    public function request(ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money): void {
            $keys = array_keys(array_filter($this->selected));
            $entries = $money->request($this->relationship, $this->user(), $keys, $this->typed);
            $this->note('money_requested', ['amount' => collect($entries)->sum('amount')]);
            $this->selected = [];
            $this->typed = [];
        });
    }

    /** The doer asks for some money on account. A note both can see, not a debt. */
    public function askMoney(ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money): void {
            $ask = $money->askOnAccount($this->relationship, $this->user(), (string) $this->ask['amount'], $this->ask['note']);
            $this->note('money_asked', ['amount' => $ask['amount']]);
            $this->ask = ['amount' => '', 'note' => ''];
        });
    }

    /** The doer asks for a share of what is accepted and still unpaid. */
    public function askPercent(int $percent, ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money, $percent): void {
            $ask = $money->askShare($this->relationship, $this->user(), $percent);
            $this->note('money_asked', ['amount' => $ask['amount']]);
        });
    }

    /** Fill the payment form from an amount that was asked for. */
    public function useAsk(string $id, DealMoney $money): void
    {
        $ask = collect($money->asks($this->relationship))->firstWhere('id', $id);
        if ($ask !== null) {
            $this->account = ['amount' => (string) $ask['amount'], 'ask' => $id, 'purpose' => 'instalment'];
        }
    }

    /** The payer hands over money on account. Reported, not proof of receipt; the recipient confirms. */
    public function payOnAccount(ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money): void {
            $payment = $money->payOnAccount($this->relationship, $this->user(), (string) $this->account['amount'], $this->payToken, $this->receipt, $this->text['note'] ?? null, $this->account['purpose'] ?: null, $this->account['ask'] ?: null);
            $this->note('money_payment_reported', ['amount' => $payment->amount]);
            $this->account = ['amount' => '', 'ask' => '', 'purpose' => 'advance'];
            $this->receipt = null;
            unset($this->text['note']);
            $this->payToken = (string) Str::uuid();
        });
    }

    public function agree(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money, $uuid): void {
            $entry = $money->approve($this->relationship, $this->user(), $uuid);
            $this->note('money_amount_agreed', ['amount' => $entry->amount]);
        });
    }

    public function disagree(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money, $uuid): void {
            $money->reject($this->relationship, $this->user(), $uuid, (string) ($this->text['no-'.$uuid] ?? ''));
            $this->note('money_amount_disagreed');
        });
    }

    /** Report a payment that was actually made; the recipient still has to confirm receiving it. */
    public function report(ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money): void {
            $payment = $money->report($this->relationship, $this->user(), $this->pay, $this->payToken, $this->receipt, $this->text['note'] ?? null, $this->purpose);
            $this->note('money_payment_reported', ['amount' => $payment->amount]);
            $this->pay = [];
            $this->receipt = null;
            $this->purpose = '';
            unset($this->text['note']);
            $this->payToken = (string) Str::uuid();
        });
    }

    public function received(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money, $uuid): void {
            $entry = $money->approve($this->relationship, $this->user(), $uuid);
            $this->note('money_receipt_confirmed', ['amount' => $entry->amount]);
        });
    }

    public function notReceived(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money, $uuid): void {
            $entry = $money->reject($this->relationship, $this->user(), $uuid, (string) ($this->text['no-'.$uuid] ?? ''));
            $this->note('money_receipt_rejected', ['amount' => $entry->amount]);
        });
    }

    public function dispute(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money, $uuid): void {
            $money->dispute($this->relationship, $this->user(), $uuid, (string) ($this->text['dispute-'.$uuid] ?? ''));
            $this->note('money_disputed');
        });
    }

    public function takeBack(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(fn () => $money->takeBack($this->relationship, $this->user(), $uuid));
    }

    public function render(DealMoney $money): View
    {
        $this->relationship->refresh();
        $items = $money->items($this->relationship);
        $ledger = $money->ledger($this->relationship);
        $user = $this->user();
        $me = (int) $user->actor->id;

        $payments = $ledger instanceof Ledger
            ? Transaction::query()->where('ledger_id', $ledger->id)->where('type', TransactionType::Payment->value)
                ->with(['fromParty.actor.user', 'toParty.actor.user', 'allocations'])->orderByDesc('id')->get()
                ->map(fn (Transaction $t) => [
                    'entry' => $t,
                    'can_confirm' => Gate::forUser($user)->allows('confirm', $t),
                    'can_dispute' => Gate::forUser($user)->allows('dispute', $t),
                    'can_take_back' => Gate::forUser($user)->allows('withdraw', $t),
                ])
            : collect();

        return view('livewire.deals.money-panel', [
            'enabled' => $money->enabled($this->relationship, $items),
            'items' => $items,
            'summary' => $money->summary($items, $money->reportedValue($this->relationship), $money->estimatedValue($this->relationship), $money->onAccount($this->relationship)),
            'asks' => $money->asks($this->relationship),
            'asProvider' => $this->roleIn($this->relationship, 'obligor'),
            'asPayer' => $this->roleIn($this->relationship, 'beneficiary'),
            'ledger' => $ledger,
            'creditSnapshot' => $ledger instanceof Ledger ? app(BalanceCalculator::class)->credit($ledger) : ['payments' => []],
            'payments' => $payments,
            'me' => $me,
            'unitsHint' => DealMoney::UNITS,
        ]);
    }

    /** Whether this person does (obligor) or receives (beneficiary) work in the Deal that falls under a payment rule. */
    private function roleIn(Relationship $deal, string $role): bool
    {
        $actorId = (int) $this->user()->actor?->id;

        return Commitment::query()->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $deal->id))
            ->when($role === 'obligor', fn ($q) => $q->where('obligor_actor_id', $actorId), fn ($q) => $q->where('beneficiary_actor_id', $actorId))->with('contractVersion.contract.sourceProposalVersion')->get()
            ->contains(fn ($c) => DealMoney::mode($c) !== null);
    }

    /** A labelled step line in the Deal's one conversation. It never decides or blocks anything. @param array<string, mixed> $meta */
    private function note(string $type, array $meta = []): void
    {
        DealNote::forDeal($this->relationship, $this->user(), $type, $meta);
    }

    private function guarded(callable $call): void
    {
        $this->resetErrorBag('money');
        try {
            $call();
        } catch (ValidationException $e) {
            $this->addError('money', (string) collect($e->errors())->flatten()->first());
        } catch (AuthorizationException) {
            abort(403);
        } catch (HttpException $e) {
            abort_if(in_array($e->getStatusCode(), [403, 404], true), $e->getStatusCode());
            $this->addError('money', $e->getMessage() !== '' ? $e->getMessage() : __('deals.money.failed'));
        } catch (\LogicException $e) {
            $this->addError('money', $e->getMessage());
        }
    }

    private function user(): User
    {
        return auth()->user();
    }
}
