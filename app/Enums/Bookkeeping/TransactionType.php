<?php

namespace App\Enums\Bookkeeping;

enum TransactionType: string
{
    /** Someone owes someone: "Majid owes 40,000,000 for Golestan". */
    case Obligation = 'obligation';

    /** Money paid from one party to another. */
    case Payment = 'payment';

    /** Money given back by the one who received a payment. */
    case Refund = 'refund';

    /** The creditor forgives (part of) a debt. */
    case WriteOff = 'write_off';

    /** Settled with goods, work or anything other than money. */
    case SettlementInKind = 'settlement_in_kind';

    /** Corrects the amount of a confirmed debt up or down. */
    case Adjustment = 'adjustment';

    /** Personal book: money received. */
    case Income = 'income';

    /** Personal book: money spent. */
    case Expense = 'expense';

    /** Personal book: money moved between one's own wallets. */
    case Transfer = 'transfer';

    /** @return list<self> */
    public static function personal(): array
    {
        return [self::Income, self::Expense, self::Transfer];
    }

    /** @return list<self> */
    public static function shared(): array
    {
        return [self::Obligation, self::Payment, self::Refund, self::WriteOff, self::SettlementInKind, self::Adjustment];
    }

    public function isPersonal(): bool
    {
        return in_array($this, self::personal(), true);
    }

    /** Types that can be applied to debts through allocations. */
    public function settlesObligations(): bool
    {
        return in_array($this, [self::Payment, self::WriteOff, self::SettlementInKind, self::Refund], true);
    }

    /**
     * Which party must agree, besides the one who recorded it.
     * Debts and payments involve both sides; a write-off is the creditor's
     * own decision, so the debtor confirms only when someone else recorded it.
     */
    public function needsCounterparty(): bool
    {
        return ! $this->isPersonal();
    }
}
