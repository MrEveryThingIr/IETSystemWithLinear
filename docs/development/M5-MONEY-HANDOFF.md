# M5 handoff — Money inside the Deal

Branch `feat/m5-deal-money`, cut from `integration/v2` at the M4 merge (`8d7d838c`). Design: [M5-MONEY-DESIGN.md](M5-MONEY-DESIGN.md). Mapping: [M4-M5-AGREEMENT-OPERATIONS-MAPPING.md](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md). Previous slice: [M4-WORK-HANDOFF.md](M4-WORK-HANDOFF.md).
Kept: no parallel financial system, no migration, no real money moves, no confirmation on anyone's behalf.

## What two people do (Deal › Money)

1. **Work is confirmed** (M4). Money exists only for Agreements whose accepted terms name a payment rule (`on_acceptance`, `after_confirmed_delivery`, `per_period`). Otherwise the tab says there is no money in this Deal and nothing is recorded.
2. **Eligible amount (computed, nothing written).** For each confirmed piece of work the tab shows what the accepted terms entitle the doer to, read from the Agreement version *the work was set up under*.
3. **Ask for payment.** The doer ticks the work they want paid (one cycle, several, or all) and presses *Ask for payment*. This records a Bookkeeping *obligation* in the Deal's shared book. It does **not** change what is owed.
4. **Report a payment.** The payer enters how much they really paid against each request (partial, or across several cycles), optionally attaches a receipt and presses *Report payment*. That records a Bookkeeping *payment* with allocations. It is *reported*, never *received*.
5. **Confirm receipt.** The recipient presses *I received it* (counts) or *Not received / wrong amount* with a reason (kept in history, counts for nothing). Either side may later dispute a confirmed entry.
6. **Settled** per piece of work when confirmed allocations equal its amount. Money never completes the Agreement.

The summary shows: Owed under the Agreement · Not yet asked for · Asked for · Reported paid · Confirmed received · Still to pay, plus how many items need attention. *Bookkeeping* (collapsed) opens the shared book.

## Which amount counts (never guessed)

`DealMoney::items` derives an amount only when sure: Toman, `price.min == price.max`, and either the price basis equals the duty's own unit (hour, day, item…) → price × confirmed quantity, or the basis is `total` and the version has exactly one duty → pro rata to the quantity confirmed. `on_acceptance` makes the duty payable once it exists. Anything else (a range, several duties on one total, another unit, no price): **no amount is invented**; the doer types it, it is only *Asked for*, and it counts as owed after the payer agrees (*Agree to this amount*). A payment cannot be reported against an amount nobody has established.
The duty form now offers the price units as suggestions so "per day" prices and "day" duties match.

## Reused (no new money code)
`CreateLedger`, `AddLedgerParty`, `RecordSharedEntry` (obligation, payment, allocations, idempotency, ledger lock), `DecideOnEntry`, `RaiseDispute`, `WithdrawEntry`, `AttachEvidence`, `BalanceCalculator`, `EntrySource`, `ConfirmationRules`, `TransactionPolicy`. The legacy `ProposeWorkBookkeepingEntry` source type (`fulfillment`) is reused, so one piece of work never gets two live entries even across the old and new paths.

## New code (small)
- `App\Support\Deals\DealMoney`: read model (eligible items, per-item state, summary). Holds nothing.
- `App\Actions\Deals\ManageDealMoney`: `ledger`, `request`, `report`, `approve`, `reject`, `dispute`, `takeBack`, each delegating to the actions above. One shared Bookkeeping book per Deal, created with the other person on first use, its uuid in `relationships.metadata.money_ledger`.
- `App\Livewire\Deals\MoneyPanel` + `deals/money-panel.blade.php`; strings `deals.money.*` in en/fa/ar/zh_CN; the Money tab in `relationships/show.blade.php` now hosts the panel.

## Guard rails and where they are tested (`tests/Feature/DealMoneyTest.php`, 7 tests, 86 assertions)
| Rule | Test |
|---|---|
| Nothing owed before work is confirmed; asking changes neither owed nor paid; only the doer asks; asking twice is one entry | one-time sale |
| Reported ≠ received; the payer cannot confirm their own report; a double submit (same form token) is one payment | one-time sale |
| Recipient confirms → settled; entries are not duplicated | one-time sale, panel test |
| Recurring: ask for 2 of 4 days; instalments covering several cycles; cannot exceed what each request leaves open; reported-but-unconfirmed money cannot be reused | recurring test |
| Rejected payment keeps its history and settles nothing; a dispute stops "settled"; strangers refused | disputed test |
| Terms give no amount → nothing owed, an amount must be typed, payment refused until the payer agrees | typed-amount test |
| No payment term → no money, requests refused, nothing in Bookkeeping | nonmonetary test |
| Amendment changes future work, never what is owed for earlier work | amendment test |
| Two accounts complete a sale through the Money tab UI; stranger cannot open the Deal | panel test |

## Evidence
- **Full suite** (Windows OpenSSL config set): **1235 tests, 1235 passed**, 14,705 assertions.
- **Focused:** `DealMoneyTest` 7 tests / 86 assertions; with `DealWorkspaceTest`, `LocalizationParityTest`, `TemporalPresentationConsistencyTest` 34 tests / 1,298 assertions.
- **Static / build:** Pint clean; PHPStan 0 errors; `npm run build` clean. No migration, so no database rehearsal was needed.
- **Browser (disposable SQLite, throwaway server, Laragon database untouched):** Alice (English) asked for payment of a 3,000,000 Toman delivery; Bob (Persian RTL) reported 1,000,000, Alice confirmed receipt ("Partly paid", 2,000,000 still to pay); Bob reported 2,000,000, Alice confirmed ("Settled", 0 to pay). Mobile 375px dark mode checked: no horizontal scroll. Fixes found by the walkthrough: Take back hidden once money moved, no localized default payment description stored, amount box cleared after reporting, translated sentences follow page direction.
- **Not done:** a recurring-work and a disputed-payment walkthrough in the browser (both covered by automated tests); the receipt upload in the browser; a parallel MySQL concurrency run.

## Known limits (carry forward)
- Book people are **Actors** (the party's stored representative), as in M4. A Business/Group manager who is not that representative cannot record in the Deal's book.
- `per_period` is treated like per confirmed work: each confirmed report is payable; no bundling by week/month yet, and there are no due dates (the Agreement carries only a payment *note*).
- Real concurrency (parallel requests) is covered by Bookkeeping's ledger lock, the source/idempotency keys and deterministic interleavings in tests; SQLite cannot run true parallel transactions, and no parallel MySQL run was made.
- Reciprocal duties with different prices on one Agreement need typed amounts (a single `price` term cannot be split).
- A payer who disagrees with an Agreement-derived request can only record *Disagree* (shown as needing attention); the amount stays owed under the Agreement and the doer must ask again.
- Adjustments (correcting an amount) and refunds exist in Bookkeeping but are not offered in the Deal's Money tab.
- Receipt attachments use `AttachEvidence` (book people only, up to 10 files).
