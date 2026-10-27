<?php

namespace App\Support\Deals;

use App\Actions\Work\ProposeWorkBookkeepingEntry;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\FulfillmentStatus;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Fulfillment;
use App\Models\Relationship;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\QuantityAmount;
use Illuminate\Support\Collection;

/**
 * What a Deal's money looks like, read from three authorities and never stored: the accepted Agreement version a piece of
 * work was set up under (what is owed), confirmed Fulfillments (that the work happened), and the Deal's shared book
 * (what was requested, reported paid and confirmed received). Entitlement is computed; Bookkeeping holds only the facts
 * people recorded, so a request alone is not a debt and a reported payment is not a received one.
 */
final class DealMoney
{
    public const MODES = ['on_acceptance', 'after_confirmed_delivery', 'per_period'];

    /** Price units the Agreement composer offers; a duty priced "per hour" must use the unit "hour". */
    public const UNITS = ['item', 'hour', 'day', 'week', 'month', 'project', 'milestone', 'session', 'kilometre'];

    public function __construct(private readonly BalanceCalculator $balances) {}

    /** The Deal's shared book, when money has been used in this Deal. */
    public function ledger(Relationship $deal): ?Ledger
    {
        $uuid = Relationship::query()->find($deal->id)?->metadata['money_ledger'] ?? null; // read fresh: a stale copy must not hide the book

        return is_string($uuid) ? Ledger::query()->where('uuid', $uuid)->first() : null;
    }

    /** @return array<string, mixed>|null the typed Terms the commitment was set up under */
    public static function terms(Commitment $commitment): ?array
    {
        $commitment->loadMissing('contractVersion.contract.sourceProposalVersion');

        return AgreementTerms::of($commitment->contractVersion);
    }

    /** Money runs only when the accepted Agreement names a payment rule; otherwise the Deal is nonmonetary. */
    public static function mode(Commitment $commitment): ?string
    {
        $mode = self::terms($commitment)['payment']['mode'] ?? null;

        return in_array($mode, self::MODES, true) ? $mode : null;
    }

    /** Money applies when the accepted Agreement names a payment rule, or work under one already exists. */
    public function enabled(Relationship $deal, ?Collection $items = null): bool
    {
        if (($items ?? $this->items($deal))->isNotEmpty()) {
            return true;
        }

        $version = $deal->contracts()->latest('id')->first()?->activeVersionRecord();
        $mode = $version !== null ? (AgreementTerms::of($version)['payment']['mode'] ?? null) : null;

        return in_array($mode, self::MODES, true);
    }

    /**
     * Everything that can be paid for in this Deal: one item per confirmed piece of work (or per duty when the Agreement
     * says payment is due on acceptance), with the amount the accepted terms entitle, when that can be known for sure.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function items(Relationship $deal): Collection
    {
        $commitments = Commitment::query()
            ->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $deal->id))
            ->with(['obligor.user', 'beneficiary.user', 'contractVersion.contract.sourceProposalVersion', 'fulfillments.occurrence'])
            ->orderBy('id')->get();
        $dutiesPerVersion = $commitments->groupBy('contract_version_id')->map->count();
        $ledger = $this->ledger($deal);

        $items = collect();
        foreach ($commitments as $commitment) {
            $mode = self::mode($commitment);
            if ($mode === null) {
                continue;
            }
            $sole = ($dutiesPerVersion[$commitment->contract_version_id] ?? 0) === 1;
            $price = self::unitPrice(self::terms($commitment));

            if ($mode === 'on_acceptance') {
                $items->push($this->item($commitment, null, 'commitment', (string) $commitment->uuid, self::amount($price, $commitment, $commitment->quantity, $commitment->quantity, $sole), $ledger));

                continue;
            }

            $verifier = self::terms($commitment)['completion']['confirmed_by'] ?? 'beneficiary';
            foreach ($commitment->fulfillments->sortBy('id') as $fulfillment) {
                $counts = $fulfillment->status === FulfillmentStatus::Accepted
                    || ($verifier === 'none' && $fulfillment->status === FulfillmentStatus::Submitted);
                if ($counts) {
                    $items->push($this->item($commitment, $fulfillment, ProposeWorkBookkeepingEntry::SOURCE_TYPE, (string) $fulfillment->uuid, self::amount($price, $commitment, $fulfillment->quantity, $commitment->quantity, $sole), $ledger));
                }
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Commitment $commitment, ?Fulfillment $fulfillment, string $sourceType, string $sourceUuid, ?int $derived, ?Ledger $ledger): array
    {
        $live = $ledger instanceof Ledger ? $this->entries($sourceType, $sourceUuid, $ledger) : collect();
        $obligation = $live->first(fn (Transaction $t) => in_array($t->status, [TransactionStatus::Pending, TransactionStatus::Confirmed, TransactionStatus::Disputed], true));
        $rejected = $obligation === null ? $live->first(fn (Transaction $t) => $t->status === TransactionStatus::Rejected) : null;
        $progress = $obligation instanceof Transaction ? $this->balances->obligation($obligation) : null;

        // Owed = what the accepted terms entitle (computed), or what the payer has confirmed when terms cannot say.
        $confirmed = $obligation?->status === TransactionStatus::Confirmed;
        $owed = $confirmed ? (int) $progress['amount'] : ($derived ?? 0);
        $paid = (int) ($progress['paid'] ?? 0);
        $outstanding = max(0, $owed - $paid);

        $state = match (true) {
            $obligation === null && $rejected !== null => 'disagreed',
            $obligation === null => 'eligible',
            $obligation->status === TransactionStatus::Disputed || ($progress['disputed'] ?? 0) > 0 => 'disputed',
            $owed > 0 && $outstanding === 0 && $paid > 0 => 'settled',
            $paid > 0 => 'part_paid',
            $obligation->status === TransactionStatus::Confirmed => 'agreed',
            default => 'requested',
        };

        return [
            'key' => $sourceType.':'.$sourceUuid,
            'source_type' => $sourceType,
            'source_uuid' => $sourceUuid,
            'commitment' => $commitment,
            'fulfillment' => $fulfillment,
            'derived' => $derived,
            'obligation' => $obligation,
            'rejected' => $rejected,
            'owed' => $owed,
            'requested' => $obligation instanceof Transaction ? (int) $progress['amount'] : 0,
            'reported' => (int) ($progress['pending'] ?? 0),
            'received' => $paid,
            'disputed' => (int) ($progress['disputed'] ?? 0),
            'outstanding' => $outstanding,
            'state' => $state,
            // A payment can be reported against it once the amount is established (terms or payer's agreement).
            'payable' => $obligation instanceof Transaction && $outstanding - (int) ($progress['pending'] ?? 0) > 0
                && ($confirmed || ($derived !== null && $obligation->status === TransactionStatus::Pending)),
        ];
    }

    /** @return Collection<int, Transaction> entries recorded for this piece of work in the Deal's book, newest first */
    private function entries(string $sourceType, string $sourceUuid, Ledger $ledger): Collection
    {
        return Transaction::query()
            ->where('ledger_id', $ledger->id)->where('type', TransactionType::Obligation->value)
            ->where('source_type', $sourceType)->where('source_uuid', $sourceUuid)
            ->orderByDesc('id')->get();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{owed: int, unrequested: int, requested: int, reported: int, received: int, outstanding: int, attention: int}
     */
    public function summary(Collection $items): array
    {
        return [
            'owed' => (int) $items->sum('owed'),
            'unrequested' => (int) $items->filter(fn ($i) => $i['obligation'] === null)->sum(fn ($i) => $i['derived'] ?? 0),
            'requested' => (int) $items->sum('requested'),
            'reported' => (int) $items->sum('reported'),
            'received' => (int) $items->sum('received'),
            'outstanding' => (int) $items->sum('outstanding'),
            'attention' => $items->filter(fn ($i) => in_array($i['state'], ['disputed', 'disagreed'], true))->count(),
        ];
    }

    /** Whole-toman price when the accepted terms fix one number in Toman; anything looser (a range, another currency) says nothing. */
    private static function unitPrice(?array $terms): ?array
    {
        $price = $terms['price'] ?? null;
        if (! is_array($price) || ! isset($price['min'], $price['max']) || (string) $price['min'] !== (string) $price['max'] || strtoupper((string) ($price['currency'] ?? '')) !== 'IRT') {
            return null;
        }
        $value = (string) $price['min'];
        if (preg_match('/^\d{1,12}(?:\.0+)?$/', $value) !== 1) {
            return null;
        }

        return ['amount' => (int) $value, 'basis' => strtolower(trim((string) ($price['basis'] ?? '')))];
    }

    /**
     * The entitled amount, or null when it cannot be known for sure: the price must be fixed, and either be per the
     * duty's own unit, or be a total for the Agreement's only duty (then pro rata to the quantity confirmed).
     *
     * @param  array{amount: int, basis: string}|null  $price
     */
    private static function amount(?array $price, Commitment $commitment, string $quantity, string $dutyQuantity, bool $sole): ?int
    {
        if ($price === null) {
            return null;
        }
        $unit = strtolower(trim((string) $commitment->unit));
        $scaled = QuantityAmount::toScaledInt($quantity);
        $scaledDuty = QuantityAmount::toScaledInt($dutyQuantity);
        if ($scaled <= 0 || $scaledDuty <= 0 || intdiv(PHP_INT_MAX, 2) < $price['amount'] * max($scaled, 1)) {
            return null;
        }

        $toman = match (true) {
            $price['basis'] !== '' && $price['basis'] === $unit => intdiv($price['amount'] * $scaled + 5000, 10000),
            $price['basis'] === 'total' && $sole => intdiv($price['amount'] * $scaled * 2 + $scaledDuty, 2 * $scaledDuty),
            default => null,
        };

        return $toman !== null && $toman > 0 ? $toman : null;
    }
}
