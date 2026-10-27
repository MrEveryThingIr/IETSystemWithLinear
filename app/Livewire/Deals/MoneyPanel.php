<?php

namespace App\Livewire\Deals;

use App\Actions\Deals\ManageDealMoney;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Deals\DealMoney;
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
            $money->request($this->relationship, $this->user(), $keys, $this->typed);
            $this->selected = [];
            $this->typed = [];
        });
    }

    public function agree(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(fn () => $money->approve($this->relationship, $this->user(), $uuid));
    }

    public function disagree(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(fn () => $money->reject($this->relationship, $this->user(), $uuid, (string) ($this->text['no-'.$uuid] ?? '')));
    }

    /** Report a payment that was actually made; the recipient still has to confirm receiving it. */
    public function report(ManageDealMoney $money): void
    {
        $this->guarded(function () use ($money): void {
            $money->report($this->relationship, $this->user(), $this->pay, $this->payToken, $this->receipt, $this->text['note'] ?? null, $this->purpose);
            $this->pay = [];
            $this->receipt = null;
            $this->purpose = '';
            unset($this->text['note']);
            $this->payToken = (string) Str::uuid();
        });
    }

    public function received(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(fn () => $money->approve($this->relationship, $this->user(), $uuid));
    }

    public function notReceived(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(fn () => $money->reject($this->relationship, $this->user(), $uuid, (string) ($this->text['no-'.$uuid] ?? '')));
    }

    public function dispute(string $uuid, ManageDealMoney $money): void
    {
        $this->guarded(fn () => $money->dispute($this->relationship, $this->user(), $uuid, (string) ($this->text['dispute-'.$uuid] ?? '')));
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
            'summary' => $money->summary($items, $money->reportedValue($this->relationship), $money->estimatedValue($this->relationship)),
            'ledger' => $ledger,
            'payments' => $payments,
            'me' => $me,
            'unitsHint' => DealMoney::UNITS,
        ]);
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
