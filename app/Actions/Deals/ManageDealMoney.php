<?php

namespace App\Actions\Deals;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\AttachEvidence;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\RaiseDispute;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Actions\Bookkeeping\WithdrawEntry;
use App\Enums\Bookkeeping\EntryOrigin;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Actor;
use App\Models\Bookkeeping\Dispute;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Fulfillment;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Bookkeeping\EntrySource;
use App\Support\Bookkeeping\Toman;
use App\Support\Deals\DealMoney;
use App\Support\TemporalCalendar;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Deal's Money step, as thin as possible: every fact is recorded by the existing Bookkeeping actions in one shared
 * book per Deal. A request records an *obligation* that is not owed until the accepted terms or the payer say so; a
 * payment is *reported* by the payer and counts only when the recipient confirms it; allocations may cover one or
 * several pieces of work. Nothing here moves real money or confirms anything on anyone's behalf.
 */
class ManageDealMoney
{
    public function __construct(
        private readonly DealMoney $money,
        private readonly RecordSharedEntry $record,
        private readonly CreateLedger $createLedger,
        private readonly AddLedgerParty $addParty,
        private readonly DecideOnEntry $decide,
        private readonly RaiseDispute $raiseDispute,
        private readonly WithdrawEntry $withdraw,
        private readonly AttachEvidence $attach,
    ) {}

    /** The Deal's shared book, created with the other person on first use. */
    public function ledger(Relationship $deal, User $user): Ledger
    {
        return DB::transaction(function () use ($deal, $user): Ledger {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($deal->id);
            $existing = $this->money->ledger($locked);
            if ($existing instanceof Ledger) {
                return $existing;
            }

            $version = $locked->contracts()->latest('id')->first()?->activeVersionRecord();
            abort_if($version === null, 422, (string) __('deals.errors.no_agreement'));
            $version->loadMissing('parties.actor.user');
            $mine = $this->actor($user);
            $other = $version->parties->map->actor->first(fn (Actor $a) => (int) $a->id !== (int) $mine->id);
            abort_unless($version->parties->contains(fn ($p) => (int) $p->actor_id === (int) $mine->id), 403);
            abort_unless($other instanceof Actor && filled($other->user?->username), 422, (string) __('deals.errors.money_no_account'));

            $ledger = $this->createLedger->execute($user, LedgerKind::Shared, Str::limit((string) __('deals.money.book_name', ['title' => $this->money->dealTitle($locked), 'names' => $mine->user->username.' & '.$other->user->username]), 150, ''));
            $this->addParty->execute($ledger, $user, (string) $other->user->username, null);
            $locked->forceFill(['metadata' => [...($locked->metadata ?? []), 'money_ledger' => $ledger->uuid]])->save();

            return $ledger;
        });
    }

    /**
     * Ask for payment for confirmed work. The doer asks; the amount is the one the accepted terms give, or one they type
     * when the terms cannot say (then it is only a request until the payer agrees).
     *
     * @param  list<string>  $keys  item keys from DealMoney::items
     * @param  array<string, string>  $typed  item key => amount, used only when the terms give none
     * @return list<Transaction>
     */
    public function request(Relationship $deal, User $user, array $keys, array $typed = []): array
    {
        $actor = $this->actor($user);
        abort_if($keys === [], 422, (string) __('deals.errors.money_choose'));
        $ledger = $this->ledger($deal, $user);

        return DB::transaction(function () use ($deal, $user, $actor, $keys, $typed, $ledger): array {
            $entries = [];
            foreach (array_values(array_unique($keys)) as $key) {
                [$type, $uuid] = array_pad(explode(':', $key, 2), 2, '');
                // Serialise requests for the same piece of work, from either side, before looking at what exists.
                $source = $type === 'commitment' ? Commitment::query()->where('uuid', $uuid)->lockForUpdate()->first() : Fulfillment::query()->where('uuid', $uuid)->lockForUpdate()->first();
                abort_if($source === null, 404);

                $item = $this->money->items($deal)->firstWhere('key', $key);
                abort_if($item === null, 422, (string) __('deals.errors.money_not_payable'));
                abort_unless((int) $item['commitment']->obligor_actor_id === (int) $actor->id, 403);
                abort_unless($item['is_due'], 422, (string) __('deals.errors.money_not_due', ['date' => $item['due_on'] !== null ? TemporalCalendar::dateLabel($item['due_on'], $user) : '']));
                abort_unless(in_array($item['state'], ['eligible', 'disagreed'], true), 422, (string) __('deals.errors.money_already_asked'));

                $amount = $item['derived'] ?? Toman::parse($typed[$key] ?? null, 'amount');
                $commitment = $item['commitment'];
                $creditor = $ledger->partyFor($commitment->obligor_actor_id);
                $debtor = $ledger->partyFor($commitment->beneficiary_actor_id);
                abort_unless($creditor !== null && $debtor !== null, 422, (string) __('deals.errors.money_book_parties'));

                $attempt = Transaction::query()->where('ledger_id', $ledger->id)->where('source_type', $item['source_type'])->where('source_uuid', $item['source_uuid'])->count() + 1;
                $entrySource = new EntrySource(EntryOrigin::IetWorkflow, $item['source_type'], $item['source_uuid'], (int) $commitment->contractVersion->version);
                $entries[] = $this->record->execute(
                    $ledger, $user, TransactionType::Obligation, $debtor, $creditor, $amount,
                    $this->today($user), (string) $commitment->title, null,
                    idempotencyKey: $entrySource->idempotencyKey().':a'.$attempt, source: $entrySource,
                );
            }

            return $entries;
        }, 3);
    }

    /**
     * The payer reports an actual payment (never proof of receipt) against the obligations it covers.
     *
     * @param  array<string, string>  $amounts  obligation uuid => whole toman
     */
    public function report(Relationship $deal, User $user, array $amounts, string $token, ?UploadedFile $receipt = null, ?string $note = null, ?string $purpose = null): Transaction
    {
        $actor = $this->actor($user);
        $ledger = $this->money->ledger($deal);
        abort_if($ledger === null, 422, (string) __('deals.errors.money_nothing_asked'));
        abort_if(trim($token) === '', 422);
        $payer = $ledger->partyFor($actor);
        abort_if($payer === null, 403);

        // The same form submitted twice is one payment: answer with the first before re-checking what is still open.
        // bk_transactions.idempotency_key holds 80 characters: hash the (deal, form token) pair to stay well inside it.
        $key = 'deal-pay:'.substr(hash('sha256', $deal->uuid.':'.trim($token)), 0, 40);
        $first = Transaction::query()->where('ledger_id', $ledger->id)->where('recorded_by_actor_id', $actor->id)->where('idempotency_key', $key)->first();
        if ($first instanceof Transaction) {
            return $first;
        }

        $allocations = [];
        foreach ($amounts as $uuid => $value) {
            if (trim($value) !== '' && trim($value) !== '0') {
                $allocations[(string) $uuid] = Toman::parse($value, 'allocations.'.$uuid);
            }
        }
        abort_if($allocations === [], 422, (string) __('deals.errors.money_enter_amount'));

        $transaction = DB::transaction(function () use ($deal, $user, $actor, $ledger, $payer, $allocations, $key, $note, $purpose): Transaction {
            $items = $this->money->items($deal);
            $payee = null;
            foreach ($allocations as $uuid => $value) {
                $item = $items->first(fn ($i) => $i['obligation']?->uuid === $uuid);
                abort_if($item === null, 422, (string) __('deals.errors.money_not_in_deal'));
                abort_unless((int) $item['commitment']->beneficiary_actor_id === (int) $actor->id, 403);
                abort_unless($item['payable'], 422, (string) __('deals.errors.money_not_established'));
                $creditor = $ledger->partyFor($item['commitment']->obligor_actor_id);
                abort_if($payee !== null && $payee->id !== $creditor?->id, 422, (string) __('deals.errors.money_one_payee'));
                $payee = $creditor;
            }

            $source = new EntrySource(EntryOrigin::IetWorkflow, 'deal_payment', (string) $deal->uuid);

            return $this->record->execute(
                $ledger, $user, TransactionType::Payment, $payer, $payee, array_sum($allocations),
                $this->today($user), $this->description($note, $purpose), null, $allocations,
                idempotencyKey: $key, source: $source,
            );
        }, 3);

        if ($receipt instanceof UploadedFile) {
            $this->attach->execute($transaction, $user, $receipt);
        }

        return $transaction;
    }

    /**
     * The doer asks for some money on account, as people do while work goes on ("send me something for the days so far").
     * It is a note both can see; it is not a debt and not a claim on any particular piece of work.
     *
     * @return array<string, mixed>
     */
    public function askOnAccount(Relationship $deal, User $user, string $amount, ?string $note = null): array
    {
        $actor = $this->actor($user);
        abort_unless($this->owedWork($deal)->contains(fn ($c) => (int) $c->obligor_actor_id === (int) $actor->id), 403);
        $value = Toman::parse($amount, 'amount');

        return DB::transaction(function () use ($deal, $actor, $value, $note): array {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($deal->id);
            $ask = ['id' => (string) Str::uuid(), 'amount' => $value, 'note' => Str::limit(trim((string) $note), 300, ''), 'by_actor_id' => $actor->id, 'at' => now()->toIso8601String(), 'status' => 'open'];
            $locked->forceFill(['metadata' => [...($locked->metadata ?? []), 'money_asks' => [...($locked->metadata['money_asks'] ?? []), $ask]]])->save();

            return $ask;
        });
    }

    /**
     * The payer hands over money on account (an advance, an instalment or a final payment) with no work attached yet.
     * It is reported, never proof of receipt: it counts when the recipient confirms. Any difference from what is earned
     * is settled at the next inspection or at the end.
     */
    public function payOnAccount(Relationship $deal, User $user, string $amount, string $token, ?UploadedFile $receipt = null, ?string $note = null, ?string $purpose = null, ?string $askId = null): Transaction
    {
        $actor = $this->actor($user);
        $mine = $this->owedWork($deal)->filter(fn ($c) => (int) $c->beneficiary_actor_id === (int) $actor->id);
        abort_if($mine->isEmpty(), 403);
        abort_if(trim($token) === '', 422);
        $value = Toman::parse($amount, 'amount');
        $ledger = $this->ledger($deal, $user);
        $payer = $ledger->partyFor($actor);
        $payee = $ledger->partyFor((int) $mine->first()->obligor_actor_id);
        abort_if($payer === null || $payee === null, 422, (string) __('deals.errors.money_book_parties'));

        $key = 'deal-acct:'.substr(hash('sha256', $deal->uuid.':'.trim($token)), 0, 40);
        $first = Transaction::query()->where('ledger_id', $ledger->id)->where('recorded_by_actor_id', $actor->id)->where('idempotency_key', $key)->first();
        if ($first instanceof Transaction) {
            return $first;
        }

        $transaction = DB::transaction(function () use ($deal, $user, $ledger, $payer, $payee, $value, $key, $note, $purpose, $askId): Transaction {
            $entry = $this->record->execute(
                $ledger, $user, TransactionType::Payment, $payer, $payee, $value, $this->today($user), $this->description($note, $purpose ?: 'advance'), null, [],
                idempotencyKey: $key, source: new EntrySource(EntryOrigin::IetWorkflow, 'deal_account', (string) $deal->uuid),
            );
            if ($askId !== null && $askId !== '') {
                $locked = Relationship::query()->lockForUpdate()->findOrFail($deal->id);
                $asks = array_map(fn ($a) => ($a['id'] ?? null) === $askId ? [...$a, 'status' => 'paid', 'payment' => $entry->uuid] : $a, $locked->metadata['money_asks'] ?? []);
                $locked->forceFill(['metadata' => [...($locked->metadata ?? []), 'money_asks' => $asks]])->save();
            }

            return $entry;
        }, 3);

        if ($receipt instanceof UploadedFile) {
            $this->attach->execute($transaction, $user, $receipt);
        }

        return $transaction;
    }

    /** @return Collection<int, Commitment> the Deal's work that falls under a payment rule */
    private function owedWork(Relationship $deal): Collection
    {
        return Commitment::query()->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $deal->id))
            ->with('contractVersion.contract.sourceProposalVersion')->get()->filter(fn (Commitment $c) => DealMoney::mode($c) !== null)->values();
    }

    /** Agree to a request or confirm a reported payment was received. Only the person still asked to decide can. */
    public function approve(Relationship $deal, User $user, string $uuid): Transaction
    {
        return $this->decide->approve($this->entry($deal, $uuid), $user);
    }

    /** Disagree with a request, or say a payment was not received / not the stated amount. A reason is required. */
    public function reject(Relationship $deal, User $user, string $uuid, string $reason): Transaction
    {
        return $this->decide->reject($this->entry($deal, $uuid), $user, $reason);
    }

    public function dispute(Relationship $deal, User $user, string $uuid, string $reason): Dispute
    {
        return $this->raiseDispute->execute($this->entry($deal, $uuid), $user, $reason);
    }

    public function takeBack(Relationship $deal, User $user, string $uuid): Transaction
    {
        return $this->withdraw->execute($this->entry($deal, $uuid), $user);
    }

    /** "Advance", "Instalment" or "Final payment" (each its own event), then the person's own words. */
    private function description(?string $note, ?string $purpose): ?string
    {
        $label = in_array($purpose, ['advance', 'instalment', 'final'], true) ? (string) __('deals.money.purpose.'.$purpose) : '';
        $text = trim(($label !== '' ? $label.($note !== null && trim($note) !== '' ? ' — ' : '') : '').trim((string) $note));

        return $text !== '' ? $text : null;
    }

    private function entry(Relationship $deal, string $uuid): Transaction
    {
        $ledger = $this->money->ledger($deal);
        $entry = $ledger instanceof Ledger ? Transaction::query()->where('uuid', $uuid)->where('ledger_id', $ledger->id)->first() : null;
        abort_if($entry === null, 404);

        return $entry;
    }

    private function actor(User $user): Actor
    {
        $actor = $user->actor;
        abort_unless($actor instanceof Actor && $user->status === 'active', 403);

        return $actor;
    }

    private function today(User $user): string
    {
        return CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->toDateString();
    }
}
