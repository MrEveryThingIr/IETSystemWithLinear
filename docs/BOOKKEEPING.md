# Bookkeeping

Bookkeeping (v1.4.0) replaces the earlier Accounting, Financial Obligation /
Settlement, money-intention and IET exchange code. It is deliberately narrow:
people record their own money and the debts and payments between them, in
**toman**, and the other side confirms. It does not compute anything from
Needs/Offers, Contracts or Planner.

## Words

| Word | Meaning | Code |
| --- | --- | --- |
| Book | A ledger: *personal* (one owner, private), *shared* (two or more people), *project* (a job with several people) | `Models\Bookkeeping\Ledger`, `LedgerKind` |
| Person in a book | A user (by username) or a name-only person without an account | `LedgerParty` (accountless parties are bare `Actor` rows) |
| Role | owner, partner (manages), member (records/confirms own entries), viewer | `PartyRole` |
| Wallet | Cash, bank card, bank account… with an opening balance; only the last 4 digits are stored | `Wallet` |
| Category | Spending/income category, system or user-made | `Category` |
| Entry | One immutable record with a type, amount, date and status | `Transaction` |
| Allocation | Which debt(s) a payment/refund/write-off pays | `Allocation` |
| Confirmation | One party's approve/reject decision on an entry | `Confirmation` |
| Dispute | An objection to a confirmed entry | `Dispute` |
| History | Append-only audit log of every change | `AuditLog` |

Tables are prefixed `bk_`. Amounts are unsigned whole toman (`Support\Bookkeeping\Toman`
parses Persian/Arabic digits and separators, max 10^15).

## Entry types

Personal book: `income`, `expense`, `transfer` (between own wallets). Undo
records the opposite entry linked by `related_transaction_id`.

Shared/project books, each between a *from* and a *to* person:

| Type | Effect on "from owes to" |
| --- | --- |
| `obligation` (debt) | + amount |
| `adjustment` (correction) | ± amount (`direction` increase/decrease) on a debt |
| `payment` | − amount, allocated to debts |
| `refund` (money returned) | − amount |
| `write_off` (debt forgiven) | − amount |
| `settlement_in_kind` | − amount (goods or work instead of money) |

Balances are always derived (`BalanceCalculator`), never stored. Only
*confirmed* entries count; *pending* and *disputed* amounts are reported next to
the confirmed figure. A debt is open / partially paid / paid from its
confirmed allocations.

## Lifecycle

```
pending ──approve by all──▶ confirmed ──object──▶ disputed
   │  └─reject──▶ rejected                │  ├─objector or partner upholds──▶ confirmed
   └─withdraw by recorder──▶ void         │  └─other side or partner cancels──▶ void
```

All status changes go through `Transaction::transitionTo()`, which checks the
allowed next state and an optimistic `version` column. Entries are never
edited or deleted; corrections are new entries.

## Rules

- **Who must confirm**: every party of the entry who has an account, except
  the person who recorded it. If nobody can confirm (e.g. the other side has
  no account) the entry is confirmed at once and labelled *recorded by one
  side*.
- **Who records**: members only for entries they are part of; owner/partners
  between any two people (both then confirm). Viewers only read.
- **Wallets** used for a payment must belong to the recorder and stay private.
  Confirmed shared-book payments through a wallet change that wallet's balance.
- **Disputes**: the objector cannot cancel the entry alone; the other side
  cannot uphold it alone.
- **Idempotency**: `(recorded_by_actor_id, idempotency_key)` is unique, so a
  double submit records once.
- **Evidence**: up to 10 files per entry, stored as Context assets of the
  book's own Context, served only to the book's people; never removable.
- Every book has its own `Context` (`ContextKind::Ledger`) for the chat,
  assets and notifications (`bookkeeping.event` outbox kind).

## Access

The Bookkeeping facility is the `money` feature surface (key kept so existing
grants carry over). Opening a specific book (`bookkeeping.books.*`) needs only
being an active person in it — so a father can confirm his son's payment
without the facility being published to him.

## The reference story

`tests/Feature/Bookkeeping/ReferenceScenarioTest.php`: the father lends Majid
40,000,000 for Golestan; 10,000,000 is paid and confirmed; another 10,000,000
is recorded with an SMS screenshot and waits; after the father confirms,
20,000,000 is left, and the history lists each step in order.

## Later (not built)

1. Delegation: a partner acting on someone's behalf (`on_behalf_of_actor_id`
   is already stored).
2. Proxy payments: A pays C on behalf of B, settling two debts at once.
3. Inviting name-only people by SMS and merging them into accounts.
4. Budgets, recurring entries, bank statement import, PDF/Excel export,
   aging reports.
5. Offering a debt from an accepted Contract fulfillment (always explicit and
   confirmed).
6. Other currencies and the **IET exchange**, designed from scratch as its own
   feature. Until then everything is toman.
