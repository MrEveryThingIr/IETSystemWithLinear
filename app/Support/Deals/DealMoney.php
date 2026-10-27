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
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a Deal's money looks like, read from three authorities and never stored: the accepted Agreement version a piece of
 * work was set up under (what is earned and when it falls due), confirmed Fulfillments (that the work happened and how much
 * of it was confirmed), and the Deal's shared book (what was requested, reported paid and confirmed received).
 * Entitlement is computed; Bookkeeping holds only the facts people recorded, so a request alone is not a debt and a
 * reported payment is not a received one.
 *
 * Per piece of work (an "item"): earned E, received R (confirmed allocations, never more than E), and
 * E = R + still-to-pay-now + not-yet-due, so no figure is counted twice.
 */
final class DealMoney
{
    public const MODES = ['on_acceptance', 'after_confirmed_delivery', 'per_period'];

    /** Price units the Agreement composer offers; a duty priced "per hour" must use the unit "hour". */
    public const UNITS = ['item', 'hour', 'day', 'week', 'month', 'project', 'milestone', 'session', 'kilometer', 'square_metre', 'metre', 'cubic_metre', 'kilogram'];

    public function __construct(private readonly BalanceCalculator $balances) {}

    /** The Deal's shared book, when money has been used in this Deal. */
    public function ledger(Relationship $deal): ?Ledger
    {
        $uuid = Relationship::query()->find($deal->id)?->metadata['money_ledger'] ?? null; // read fresh: a stale copy must not hide the book

        return is_string($uuid) ? Ledger::query()->where('uuid', $uuid)->first() : null;
    }

    /** The Deal's own name for its book: what was agreed (or what the post was), never a generic label. */
    public function dealTitle(Relationship $deal): string
    {
        $version = $deal->contracts()->latest('id')->first()?->activeVersionRecord();
        $title = $version !== null ? trim((string) (AgreementTerms::of($version)['title'] ?? '')) : '';
        if ($title === '') {
            $title = trim((string) ($deal->originatingIntent?->publishedVersion?->snapshot['core']['title'] ?? ''));
        }

        return $title !== '' ? $title : (string) __('deals.agreement');
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
     * says payment is due on acceptance), with the amount the accepted terms entitle, when that can be known for sure,
     * and the day it falls due.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function items(Relationship $deal): Collection
    {
        $commitments = Commitment::query()
            ->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $deal->id))
            ->with(['obligor.user', 'beneficiary.user', 'contractVersion.contract.sourceProposalVersion', 'fulfillments.review'])
            ->orderBy('id')->get();
        $dutiesPerVersion = $commitments->groupBy('contract_version_id')->map->count();
        $ledger = $this->ledger($deal);
        $recorded = $this->recorded($ledger);

        $items = collect();
        foreach ($commitments as $commitment) {
            $mode = self::mode($commitment);
            if ($mode === null) {
                continue;
            }
            $terms = self::terms($commitment);
            $sole = ($dutiesPerVersion[$commitment->contract_version_id] ?? 0) === 1;
            $price = self::unitPrice($terms);
            $zone = (string) ($terms['time']['timezone'] ?? config('app.timezone'));

            if ($mode === 'on_acceptance') {
                $due = CarbonImmutable::parse($commitment->created_at)->setTimezone($zone)->startOfDay();
                $items->push($this->item($commitment, null, 'commitment', (string) $commitment->uuid, $commitment->quantity, self::amount($price, $commitment, $commitment->quantity, $commitment->quantity, $sole), $due, ['rule' => 'on_acceptance'], $recorded, $zone));

                continue;
            }

            $verifier = $terms['completion']['confirmed_by'] ?? 'beneficiary';
            foreach ($commitment->fulfillments->sortBy('id') as $fulfillment) {
                $counts = $fulfillment->status === FulfillmentStatus::Accepted
                    || ($verifier === 'none' && $fulfillment->status === FulfillmentStatus::Submitted);
                if (! $counts) {
                    continue;
                }
                $quantity = $fulfillment->status === FulfillmentStatus::Accepted ? $fulfillment->confirmedQuantity() : (string) $fulfillment->quantity;
                [$due, $rule] = self::dueOn($mode, $terms, $fulfillment, $zone);
                $items->push($this->item($commitment, $fulfillment, ProposeWorkBookkeepingEntry::SOURCE_TYPE, (string) $fulfillment->uuid, $quantity, self::amount($price, $commitment, $quantity, $commitment->quantity, $sole), $due, $rule, $recorded, $zone));
            }
        }

        return $items;
    }

    /**
     * What has been reported but not yet confirmed, priced the same way: not owed, shown so reported work is visible too.
     * Only reports whose price can be known for sure count; the rest are not guessed.
     */
    public function reportedValue(Relationship $deal): int
    {
        $commitments = Commitment::query()
            ->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $deal->id))
            ->with(['contractVersion.contract.sourceProposalVersion', 'fulfillments'])->get();
        $dutiesPerVersion = $commitments->groupBy('contract_version_id')->map->count();
        $total = 0;
        foreach ($commitments as $commitment) {
            if (self::mode($commitment) === null) {
                continue;
            }
            $price = self::unitPrice(self::terms($commitment));
            $sole = ($dutiesPerVersion[$commitment->contract_version_id] ?? 0) === 1;
            $verifier = self::terms($commitment)['completion']['confirmed_by'] ?? 'beneficiary';
            foreach ($commitment->fulfillments as $fulfillment) {
                $waiting = in_array($fulfillment->status, [FulfillmentStatus::Submitted, FulfillmentStatus::ClarificationRequested], true) && ! ($verifier === 'none' && $fulfillment->status === FulfillmentStatus::Submitted);
                if ($waiting) {
                    $total += self::amount($price, $commitment, (string) $fulfillment->quantity, $commitment->quantity, $sole) ?? 0;
                }
            }
        }

        return $total;
    }

    /**
     * A rough running figure from the work log: what the doer's daily estimates add up to, less what has already gone to
     * inspection (confirmed or waiting), at the agreed price. A guide to how much may be owed; never owed, never confirmed.
     */
    public function estimatedValue(Relationship $deal): int
    {
        $commitments = Commitment::query()
            ->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $deal->id))
            ->with(['contractVersion.contract.sourceProposalVersion', 'fulfillments.review'])->get();
        $dutiesPerVersion = $commitments->groupBy('contract_version_id')->map->count();
        $total = 0;
        foreach ($commitments as $commitment) {
            if (self::mode($commitment) === null) {
                continue;
            }
            $inspected = $commitment->fulfillments->reduce(function (string $sum, Fulfillment $f) {
                return match ($f->status) {
                    FulfillmentStatus::Accepted => QuantityAmount::add($sum, $f->confirmedQuantity()),
                    FulfillmentStatus::Submitted, FulfillmentStatus::ClarificationRequested => QuantityAmount::add($sum, (string) $f->quantity),
                    default => $sum,
                };
            }, QuantityAmount::normalize('0'));
            $rough = QuantityAmount::subtractFloorZero(WorkLog::for($commitment)['estimate'], $inspected);
            if (QuantityAmount::compare($rough, '0') > 0) {
                $total += self::amount(self::unitPrice(self::terms($commitment)), $commitment, $rough, $commitment->quantity, ($dutiesPerVersion[$commitment->contract_version_id] ?? 0) === 1) ?? 0;
            }
        }

        return $total;
    }

    /**
     * @param  array{rule: string, period?: string}  $rule
     * @param  array{obligations: Collection<string, Collection<int, Transaction>>, allocations: Collection<int, Collection<int, object>>}  $recorded
     * @return array<string, mixed>
     */
    private function item(Commitment $commitment, ?Fulfillment $fulfillment, string $sourceType, string $sourceUuid, string $quantity, ?int $derived, ?CarbonImmutable $due, array $rule, array $recorded, string $zone): array
    {
        $live = $recorded['obligations']->get($sourceType.':'.$sourceUuid, collect());
        $obligation = $live->first(fn (Transaction $t) => in_array($t->status, [TransactionStatus::Pending, TransactionStatus::Confirmed, TransactionStatus::Disputed], true));
        $rejected = $obligation === null ? $live->first(fn (Transaction $t) => $t->status === TransactionStatus::Rejected) : null;
        $progress = $obligation instanceof Transaction ? $this->balances->obligation($obligation) : null;

        // Earned = what the accepted terms entitle (computed), or what the payer has confirmed when terms cannot say.
        $confirmed = $obligation?->status === TransactionStatus::Confirmed;
        $earned = $confirmed ? (int) $progress['amount'] : ($derived ?? 0);
        $received = min($earned, (int) ($progress['paid'] ?? 0));
        $isDue = $due !== null && $due->lte(CarbonImmutable::now($zone)->startOfDay());
        $unpaid = max(0, $earned - $received);

        $state = match (true) {
            $obligation === null && $rejected !== null => 'disagreed',
            $obligation === null && $earned > 0 && ! $isDue => 'not_due',
            $obligation === null => 'eligible',
            $obligation->status === TransactionStatus::Disputed || ($progress['disputed'] ?? 0) > 0 => 'disputed',
            $earned > 0 && $unpaid === 0 && $received > 0 => 'settled',
            $received > 0 => 'part_paid',
            $obligation->status === TransactionStatus::Confirmed => 'agreed',
            default => 'requested',
        };

        return [
            'key' => $sourceType.':'.$sourceUuid,
            'source_type' => $sourceType,
            'source_uuid' => $sourceUuid,
            'commitment' => $commitment,
            'fulfillment' => $fulfillment,
            'quantity' => $quantity,
            'derived' => $derived,
            'due_on' => $due,
            'due_rule' => $rule,
            'is_due' => $isDue,
            'obligation' => $obligation,
            'rejected' => $rejected,
            'allocations' => $obligation instanceof Transaction ? $recorded['allocations']->get($obligation->id, collect()) : collect(),
            'owed' => $earned,
            'earned' => $earned,
            'requested' => $obligation instanceof Transaction ? (int) $progress['amount'] : 0,
            'reported' => (int) ($progress['pending'] ?? 0),
            'received' => $received,
            'disputed' => (int) ($progress['disputed'] ?? 0),
            'outstanding' => $unpaid,
            'due_unpaid' => $isDue ? $unpaid : 0,
            'future' => $isDue ? 0 : $unpaid,
            'state' => $state,
            // A payment can be reported against it once the amount is established (terms or payer's agreement).
            'payable' => $obligation instanceof Transaction && $unpaid - (int) ($progress['pending'] ?? 0) > 0
                && ($confirmed || ($derived !== null && $obligation->status === TransactionStatus::Pending)),
        ];
    }

    /**
     * Every obligation recorded for Deal work, grouped by the work it is for, with the payments allocated to each.
     *
     * @return array{obligations: Collection<string, Collection<int, Transaction>>, allocations: Collection<int, Collection<int, object>>}
     */
    private function recorded(?Ledger $ledger): array
    {
        if (! $ledger instanceof Ledger) {
            return ['obligations' => collect(), 'allocations' => collect()];
        }
        $obligations = Transaction::query()->where('ledger_id', $ledger->id)->where('type', TransactionType::Obligation->value)
            ->whereIn('source_type', [ProposeWorkBookkeepingEntry::SOURCE_TYPE, 'commitment'])->orderByDesc('id')->get();
        $allocations = DB::table('bk_allocations as a')->join('bk_transactions as t', 't.id', '=', 'a.transaction_id')
            ->whereIn('a.obligation_id', $obligations->pluck('id'))->orderBy('t.id')
            ->get(['a.obligation_id', 'a.amount', 't.uuid', 't.status', 't.occurred_on', 't.type'])->groupBy('obligation_id');

        return ['obligations' => $obligations->groupBy(fn (Transaction $t) => $t->source_type.':'.$t->source_uuid), 'allocations' => $allocations];
    }

    /**
     * The day work falls due, from the payment rule of the Agreement version it was set up under:
     * after confirmed delivery = the day it was confirmed; every week/month = the last day of the calendar week (Monday to
     * Sunday) or month in which the work period ended. A week or month is never guessed from attendance.
     *
     * @return array{0: ?CarbonImmutable, 1: array{rule: string, period?: string}}
     */
    private static function dueOn(string $mode, ?array $terms, Fulfillment $fulfillment, string $zone): array
    {
        $confirmedAt = $fulfillment->reviewed_at ?? $fulfillment->review?->reviewed_at ?? $fulfillment->updated_at;
        if ($mode === 'per_period') {
            $period = (string) ($terms['payment']['period'] ?? 'week');
            $worked = CarbonImmutable::parse($fulfillment->actual_end_at ?? $confirmedAt)->setTimezone($zone);
            $end = $period === 'month' ? $worked->endOfMonth() : $worked->endOfWeek(CarbonImmutable::SUNDAY);

            return [$end->startOfDay(), ['rule' => 'per_period', 'period' => $period]];
        }

        return [CarbonImmutable::parse($confirmedAt)->setTimezone($zone)->startOfDay(), ['rule' => 'after_confirmed_delivery']];
    }

    /**
     * Each figure counts once. Per piece of work earned = received + still due + not yet due:
     *  - estimated: the doer's rough daily progress not yet taken to inspection, at the agreed price (a guide; never owed)
     *  - reported_value: work submitted for inspection that the receiver has not decided on yet (not owed)
     *  - earned: confirmed work at the accepted price
     *  - payable: the part of earned whose due day has come, whatever has been paid
     *  - outstanding_due: payable less what is confirmed received
     *  - not_yet_due: earned work whose due day is still ahead
     *  - requested / reported_paid / received: what has been asked, reported paid, and confirmed received
     *  - settled: earned value of work fully paid, and how many pieces
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    public function summary(Collection $items, int $reportedValue = 0, int $estimatedValue = 0): array
    {
        $dueItems = $items->filter(fn ($i) => $i['is_due']);
        $settled = $items->filter(fn ($i) => $i['state'] === 'settled');

        return [
            'estimated' => $estimatedValue,
            'reported_value' => $reportedValue,
            'earned' => (int) $items->sum('earned'),
            'payable' => (int) $dueItems->sum('earned'),
            'outstanding_due' => (int) $items->sum('due_unpaid'),
            'not_yet_due' => (int) $items->sum('future'),
            'requested' => (int) $items->sum('requested'),
            'reported' => (int) $items->sum('reported'),
            'received' => (int) $items->sum('received'),
            'settled' => (int) $settled->sum('earned'),
            'settled_count' => $settled->count(),
            'items' => $items->count(),
            // kept for existing callers
            'owed' => (int) $items->sum('earned'),
            'unrequested' => (int) $items->filter(fn ($i) => $i['obligation'] === null && $i['is_due'])->sum(fn ($i) => $i['derived'] ?? 0),
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
