# M5 accounting correctness checkpoint — 2026-10-29

Status: **blocked on accounting model change; not fixed**. Stop before the Money UI redesign, estimation experiment, merge, deployment, or M6, as requested by the owner.

## Baseline and owner-data protection

Repository: `C:\laragon\www\EveryThing`; branch: `feat/m5-deal-money`.
Local HEAD and remote branch both verified as `f39b50deb376d2b38514a89f86accd2d88c2c60d`. Working tree was clean before this investigation. PHP 8.4.12 and Composer 2.10.3 are available; Boost is already a declared development dependency. No application changes or dependency installation were performed during this diagnostic checkpoint.

The owner's database was inspected only through aggregate SELECT queries inside an explicitly read-only transaction, then rolled back. At inspection time, Deal on-account records comprise one confirmed payment of **1,000,000 Toman** and one void payment of **1,000,000 Toman**; neither has allocations or a related entry. No matching Deal payment or refund records appeared in that aggregate query. This is an inventory of the selected source types, not a full audit of every ledger or a conclusion that all other records are correct. No owner data was migrated, reset, corrected, or deleted.

## Verified source diagnosis

- `ManageDealMoney::payOnAccount` records Payment with the actual payer and recipient and an explicit empty allocation list. It does not record an obligation. Preserve these identities, confirmations and receipt assets.
- `BalanceCalculator::netByPair` subtracts the entire confirmed payment from obligations, regardless of allocation. `pairs` turns a negative aggregate into a debtor/creditor reversal; `forParty` likewise reports it as a reverse receivable/payable. Thus receipt confirmation of an advance creates a reverse debt in the display without an accepted reverse obligation.
- `RecordSharedEntry::allocate` runs only when a settlement entry is recorded. Recording or confirming a later obligation does not reconcile a prior advance. `BalanceCalculator::obligation` reads persisted allocations, so it can show unpaid while the pair aggregate looks settled or reversed.
- `DealMoney::summary` separately offsets on-account money at summary level. It does not create allocations in the authoritative book. This is the architectural source of the disagreement between the Deal and Bookkeeping.
- `RecordSharedEntry` permits refunds without a related payment and without allocations. Refund allocation targets an obligation, not the original payment or its residual advance. There is no enforced distinction between return of unused advance, reversal of an allocated settlement, and payment satisfying an independent reverse obligation.
- `BookkeepingRulesTest::test_corrections_are_new_entries_never_edits` explicitly expects a 250,000 overpayment to become a reverse receivable; `test_name_only_people_are_recorded_by_one_side_and_count_at_once` expects a payment without an obligation to create a receivable. Those expectations conflict with the newly required behavior and must change alongside a complete model correction.

## Required accounting model change

A single signed net cannot represent both enforceable debt and non-repayable advance credit, especially when both parties have obligations. Correcting only the sign, clamping a net balance, or implementing a second calculation in the Deal would violate the requirements.

The Bookkeeping domain needs an explicit, auditable credit/application lifecycle and a return-of-credit relationship. Existing transaction and allocation tables may be reusable, but the present allocation and confirmation model does not implement these semantics. Merely adding more display fields is insufficient. This is a larger change to the shared accounting model, so implementation stops at this checkpoint under the owner's stop instruction.

Before implementing it, settle and encode these rules in Bookkeeping itself:

1. Keep directional accepted obligations separate from directional cash credit. Advance received is evidence of cash movement, never an automatic reverse obligation.
2. Apply available confirmed credit only to accepted obligations in the same payer/payee direction, with a deterministic order, ledger-level serialization, idempotency and an audit event. Pending/rejected/disputed/void facts must not silently consume confirmed credit. Define restoration behavior when an entry is disputed or voided after allocation.
3. Every unit of a payment must be accounted for exactly once as applied, returned, or available credit. Do not also subtract the full payment independently from debt. Allocations and per-obligation progress must use the same calculation as pair and Deal summaries.
4. A return must identify the credit/payment it returns. A contractual refund obligation must be explicitly accepted and remain distinct from a voluntary advance return. Reversing an applied settlement and returning unused credit have different effects on open work obligations.
5. Preserve genuine reverse obligations and reciprocal positions. Do not consume a credit in the opposite direction merely because a signed aggregate permits it. Show both parties complementary debt and credit categories.
6. Add the MySQL regression matrix requested by the owner, including late obligations, partial applications, overpayment, both directions, reciprocal debts, refunds of unused and applied amounts, confirmation/rejection/dispute, duplicate submissions and real database constraints.

## Safe reconciliation proposal (not executed)

- Back up and snapshot transaction, allocation, confirmation, dispute, evidence and audit tables before any authorized write. Capture IDs and versions for the affected ledgers.
- Generate a read-only dry-run report per directed party pair. List each confirmed payment, confirmed allocation, accepted obligation, credit return and unresolved relationship. Preserve void/rejected records as history; never count the void 1,000,000 record as available credit.
- Treat the confirmed unallocated 1,000,000 Deal payment as a candidate advance credit, not an automatic repayable debt. Verify its receipt and the accepted work facts before proposing applications. No inferred obligation may be inserted to make the arithmetic balance.
- Require review of ambiguous historical refunds/settlements; do not guess their purpose from descriptions, opposite direction, timestamps or totals.
- Apply only reviewed proposals in an authorized transaction under ledger locks with deterministic reconciliation keys and append-only audit evidence. Link credit applications/returns without rewriting original payment identities, amounts, receipt evidence or confirmations.
- Recompute per-obligation, directed pair, party and Deal views and assert conservation of credit, no double application and complementary positions. A rerun must produce no additional applications. Publish before/after evidence before any release.

## Verification scope

This checkpoint is diagnostic, not a passing financial regression suite. No UI, localization, media, estimation, Pint/PHPStan/frontend or broad regression completion is claimed. Those gates remain required after the accounting model is implemented. PR management remains with ChatGPT.

### Disposable MySQL reproduction

Reproduced through the real `CreateLedger`, `AddLedgerParty`, `RecordSharedEntry` and `DecideOnEntry` actions on MySQL **8.4.3**, using a freshly migrated disposable database populated with two factory accounts and their shared ledger:

| Step | Actual pair position from recipient side | Actual per-obligation state | Required result |
|---|---|---|---|
| Payer reports 1,000,000; recipient confirms | Recipient owes 1,000,000 | No obligation; zero allocations | No debt; 1,000,000 advance credit |
| Later 600,000 obligation is accepted by payer | Recipient owes 400,000 | 600,000 unpaid; zero allocations | 600,000 applied once; 400,000 advance credit |
| Recipient returns 400,000 using confirmed unallocated Refund | Pair net zero | 600,000 still unpaid | Remaining credit zero; work paid by the earlier advance |

Payment payer/payee identities were correct throughout. Refund had null `related_transaction_id`, zero allocations and only `source: manual` metadata. The pair balance can therefore be zero while its accepted obligation remains fully unpaid. This reproduces the cross-view inconsistency as well as the reversed debt. These are observed failures, not passing assertions of the requested behavior.

## Follow-up authorization and implementation

The owner accepted the diagnosis above and subsequently authorized a bounded correction to the existing Bookkeeping model. The original diagnostic stop is superseded by that authorization. See [the correction record](M5-ACCOUNTING-CORRECTION.md) and the latest [M5 handoff](M5-MONEY-HANDOFF.md). The original reproduction and source-record inventory above remain historical evidence, not a statement that the later correction is still blocked.
