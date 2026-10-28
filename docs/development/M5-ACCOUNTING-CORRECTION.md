# M5 bounded accounting correction — 2026-10-29

This implements the accounting domain correction authorized after [the accepted diagnosis](M5-ACCOUNTING-CHECKPOINT.md). The Money tab redesign, estimation experiment, merge, deployment and M6 remain out of scope.

## Authority and storage

`CreditAccounting::snapshot` is the authoritative shared-book projection. `BalanceCalculator` delegates obligation progress, directed pairs and party positions to it. Deal items read the same obligation rows; the summary does not perform a second advance allocation. Bookkeeping and Deal payment history expose the same payment applications, available credit, reserved unused returns and confirmed returns.

No migration is required. No balance column, second ledger, parallel transaction system or new table was introduced. Existing immutable transactions, `bk_allocations`, confirmations, disputes and append-only audit logs remain the source facts. `related_transaction_id` identifies a refund's original payment; `meta.return_mode` distinguishes `unused` from `applied`. New shared entries carry `meta.credit_policy = fifo_v1`. Historical records are not backfilled with this marker.

Stored allocation rows are **instructions**, not the final applied total. The read-only projection caps instructions by source cash and accepted obligation capacity, then applies eligible residual credit. Automatic applications are derived maps keyed by source payment and obligation, not new or modified allocation rows. This deliberately avoids rewriting allocations when a payment, obligation or correction is later disputed. Each supported recording, approval, rejection, withdrawal, dispute or resolution appends a `credit.projected` audit event with the triggering transaction version, policy version and complete application snapshot. Original payment identities, dates, descriptions, evidence and confirmation records stay intact.

## Financial invariants

- Only a confirmed obligation creates confirmed debt. Pending requests, rejected/void obligations and disputed obligations do not create confirmed debt.
- Only confirmed payments provide usable cash credit. An advance never creates an opposite-direction debt or an inferred contractual refund obligation.
- A payment applies only to obligations in its own payer/payee direction. Reciprocal obligations remain two distinct directed positions; party `i_owe` and `owed_to_me` remain complementary across the two accounts. `net` is a convenience, not an instruction to net away reciprocal claims.
- For every confirmed payment: **amount = applied + confirmed returned + available credit + unused credit reserved for pending/disputed returns**. Applied equals the sum of that payment's effective applications. No amount may appear twice in this partition.
- Effective applications cannot exceed the accepted, adjusted obligation amount. Remaining obligation debt equals that amount less effective cash and noncash settlements. Write-offs and in-kind settlements never create advance credit and are not reported as received cash.
- Explicit allocation instructions have priority. Residual eligible credit uses deterministic `(occurred_on, id)` order of payments and obligations. New backdated facts or status/correction changes can change the effective map; the resulting map is audited. This is recomputation of source facts, not an irreversible rewrite of earlier application instructions.
- A newly accepted later obligation can consume eligible prior credit exactly once. Multiple work cycles share source capacity rather than independently subtracting the same advance.
- A return requires an identified confirmed source payment in the reverse cash direction, a nonempty reason and sufficient source capacity. Unused returns cannot reopen paid work. Applied returns name an accepted obligation and reduce that source payment's application there; the returned amount cannot immediately reapply from the same source to that obligation.
- A genuine contractual refund obligation is a separately accepted reverse obligation, settled by a payment in that direction. It is never inferred from excess cash.

## Lifecycle rules

| Change | Confirmed effect |
|---|---|
| Payment pending/rejected/void | No cash application or usable advance; original entry remains in history |
| Confirmed payment disputed | Its applications and usable credit leave the confirmed position; accepted obligations reopen |
| Payment dispute upheld | Recompute its confirmed applications once from current accepted facts |
| Obligation pending/rejected/void/disputed | No confirmed debt or effective cash application to it; released eligible credit can cover other accepted work in the same direction |
| Obligation dispute upheld | Restore it to deterministic allocation order; never exceed source capacity |
| Confirmed decrease | Reduce accepted debt; excess cash becomes available credit, not reverse debt |
| Competing decreases | Revalidate under lock at confirmation; a second decrease cannot make accepted debt negative |
| Unused return pending/disputed | Reserve its unused tranche; exclude it from available credit; do not reduce confirmed debt or count it as confirmed returned |
| Unused return rejected/void | Release the reservation and recompute eligible applications |
| Applied return pending/disputed | Do not reverse confirmed applications yet; reserve the named applied tranche against another return request |
| Applied return confirmed | Reduce source cash and its named obligation application; reopen that part of the accepted obligation |
| Confirmed return later disputed | Exclude the return from confirmed returned cash and recompute; unused returns stay reserved until resolution |
| Source payment disputed/void after a return | Source applications and credit are excluded; the linked return record and cash evidence remain traceable. No reverse debt is invented. Upholding the source recomputes using its still-confirmed linked returns |

Return capacity is revalidated at confirmation and dispute-uphold, not only when the form is submitted. If intervening work/corrections leave insufficient capacity, confirmation is refused; reject/withdraw the proposal and record the reviewed correct return instead. Old unlinked refunds are flagged for review and disable automatic residual allocation in that book; no source is guessed. Existing explicit instructions remain visible, so such a book must be reviewed before relying on its financial position.

## Work value versus accepted debt

Confirmed inspection value at accepted Agreement rates remains an earnings fact. It does not silently create a confirmed Bookkeeping obligation. A known price still supplies a request amount, but that obligation must now be approved before it becomes payable or consumes credit. The existing payer approval action is reused; no Money layout redesign was introduced.

Deal `earned` may therefore include `unrecorded_earnings` (confirmed work whose amount has not become an accepted ledger obligation). `owed`, outstanding and due balances use only accepted Bookkeeping obligations. For recognized work: **earned = applied cash received + noncash settlement + remaining accepted debt + unrecorded earnings**. `received_total` additionally exposes confirmed cash net of identified returns, including unapplied advances. The former summary-only `credit_applied` field remains zero for compatibility because effective applications already appear in `received`.

Updated legacy expectations are limited to demonstrable conflicts: overpayment-as-reverse-debt, cash payment without obligation as a receivable, audit sequence additions, and Deal tests that treated pending/unrecorded obligations as confirmed debt or applied summary credit before ledger acceptance. Amendment, permissions, rejection, evidence, partial-payment and source immutability assertions remain.

## Concurrency and duplicate requests

Writers acquire the ledger row before transaction/dispute/source locks. Idempotency is checked again with a current read after the ledger lock, backed by the existing unique actor/request key. Pre-lock checks do not hold index-gap locks. Confirmation, adjustment and return-capacity reads use current reads so an outer repeatable-read snapshot cannot hide a concurrent prior commit. Amount changes and lifecycle updates retain transaction version checks.

Ordinary projection reads use one database transaction for a consistent source snapshot and never write. Audited writer projections use current locking reads inside the ledger transaction. No projection cache outlives the source facts. Duplicate approved confirmations by the same confirmer return the existing confirmed entry without another confirmation or audit event.

`tools/m5-credit-mysql.php` creates a uniquely prefixed local disposable database, runs the focused matrix and starts independent PHP workers. The final concurrency harness deliberately establishes old outer snapshots and verifies two simultaneous ledger-lock waiters through MySQL `performance_schema.data_lock_waits` before releasing the parent lock. It checks duplicate payments, competing credit returns and concurrent obligation approvals. It drops only its own captured disposable database in `finally`. Do not run it against the normal Laragon database.

## Historical records and dry run

`php tools/m5-credit-dry-run.php` performs SELECTs inside an explicitly read-only MySQL transaction and rolls it back. It has no apply option. The locally generated owner report is `storage/release-acceptance/m5/credit-dry-run.json` (ignored by Git). It includes the confirmed **1,000,000 Toman** Deal advance as **1,000,000 available, zero applied, automatic reconciliation disabled**. The void 1,000,000 entry contributes zero. A separate pre-existing small payment is also inventoried; this broader report is not limited to Deal-source payments.

Each ledger includes current treatment, source and obligation IDs/UUIDs, review issues and a separately labelled hypothetical historical-credit allocation. The hypothetical branch only enables the policy in memory; it changes no transaction, allocation, confirmation, audit record or metadata. No historical correction was applied. Any future application of historical credit requires a reviewed proposal and explicit owner authorization, following the checkpoint's backup and reconciliation procedure.

## Compatibility and rollback

There are no schema migrations or data backfills to roll back. Tests load legacy rows without new metadata, preserve their attributes and allocation rows, and verify that a hypothetical historical reconciliation cannot alter stored data. Existing PHP model APIs and table constraints remain usable. New return source/mode arguments are appended to `RecordSharedEntry::execute`; existing payment and obligation callers remain compatible. Refund callers now must provide source and reason instead of recording an ambiguous return.

A safe operational rollback disables the new writing paths while retaining the corrected reader and all audit/evidence history. Do not remove source links or delete new refund/application audit history. Rolling back to the pre-correction balance formula would reintroduce the known bug and cannot be called financially safe. No deploy, writer-disable operation or rollback was executed here.

## Verification

Final gate results are recorded in the M5 handoff. The tooling and tests are reproducible from this branch. Browser/mobile visual acceptance and the later Money simplification remain separate work; bilingual component rendering and source controls are covered by regression tests.

### Final verification results

The final disposable MySQL matrix passed **54 tests / 862 assertions**. All three parallel scenarios passed with **two concurrent MySQL ledger-lock waiters** demonstrated per scenario and deliberately stale outer snapshots. The harness dropped its captured disposable database. The later noncash guide correction passed its focused Deal regression (1 / 6 on SQLite); its backend cash/noncash partition is covered by the MySQL matrix. Full Pint passed, PHPStan found zero errors, frontend build passed, and localization parity passed (7 / 360). The complete financial correction is in `22196392`; the noncash guide follow-up is in `3f61d0dd`. See the handoff for scope and query-budget evidence.
