# Phase 13 — Personal Accounting v1

## Status

Remote implementation complete and green on `feat/ideal-v1-13-personal-accounting`.

Final runtime checkpoint:

~~~text
SHA: c5a45f7d4178637e322855e71318709610e835b8
GitHub Actions: 36042662030
469 tests / 2714 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Kernel checkpoint:

~~~text
SHA: 81f66c6934c39cbaad26d5363deaad9239777a75
GitHub Actions: 36041431006
465 tests / 2669 assertions
~~~

Baseline: Phase 12 integration merge `6769b8632084f33a464145ae7501f2a4e88f5531`.

## Purpose

Give a normal user immediate everyday money tracking through plain actions while preserving rigorous, balanced and immutable accounting truth underneath.

Phase 13 is intentionally **Personal Context accounting only**. Shared/Relationship financial authority remains deferred to the explicit Financial Obligation + Settlement bridge.

## Implemented kernel

Phase 13 adds:

- `MonetaryUnit`;
- `Ledger`;
- `Account`;
- immutable posted `JournalEntry`;
- immutable `JournalLine`;
- opening-balance, expense, income and transfer Actions;
- reversal and correction Actions;
- derived Account balances;
- day/week/month/year income/expense/net summaries;
- Personal-Ledger authorization;
- accounting source projection into the unified Context Timeline.

All amounts are stored as integer **minor units** using the MonetaryUnit exponent. Runtime money parsing/formatting does not use floating-point arithmetic.

## Double-entry invariant

Every posted Journal Entry:

- belongs to exactly one Ledger;
- has at least two lines;
- uses Accounts from that Ledger only;
- requires exactly one positive debit or credit per line;
- must have total debits equal total credits before the transaction commits.

Normal users do not enter debit/credit terminology. Friendly Actions generate balanced Journal Lines internally.

## Friendly product actions

The normal Accounting surface exposes:

- **Create personal ledger**;
- **Opening balance**;
- **Add expense**;
- **Add income**;
- **Transfer**;
- add another cash/bank Asset Account;
- current balances;
- day/week/month/year summaries;
- immutable activity history;
- **Reverse entry** for mistakes.

The advanced sidebar links directly to **Accounting**.

## Story proof — Bob

Bob creates a personal EUR Ledger.

He records:

1. opening Cash balance: EUR 1000.00;
2. Work gloves expense: EUR 25.00;
3. Service income: EUR 100.00;
4. adds Bank;
5. transfers EUR 200.00 Cash → Bank.

The derived result is:

- Cash: EUR 875.00;
- Bank: EUR 200.00;
- total Asset balance: EUR 1075.00;
- month income: EUR 100.00;
- month expense: EUR 25.00;
- month net: EUR 75.00.

No debit/credit wording is required in the routine UI.

## Reversal and correction

Posted Journal Entries and Lines are immutable.

A mistake is handled by:

- posting a reversing Journal Entry;
- optionally posting a replacement/correction entry that references the original.

Reversal preserves the original row and restores its accounting effect through equal-and-opposite lines.

Direct update/delete of posted accounting history is rejected by model invariants.

## Timeline composition

Personal Journal Entries project into Phase 11's unified Context Timeline.

Timeline:

- stores no duplicate accounting transaction;
- shows a friendly accounting event label/description;
- links back to the source Personal Ledger/Journal Entry.

JournalEntry/JournalLine remain accounting authority.

## Authorization boundary

Personal Accounting v1 belongs only to the authenticated Actor's Personal Context.

Another Actor cannot:

- select Bob's Ledger;
- view it;
- manage it;
- post Journal Entries into it.

Relationship collaboration, Planner completion, Conversation wording, or Content prose does not create shared debt, obligation, invoice, payment, settlement, or ownership.

Those authoritative bridges remain later roadmap work.

## Remote validation

Final CI `36042662030` proves:

- PHPUnit: **469 passed / 2714 assertions**;
- PHPStan: clean;
- Pint: clean;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler/database-queue smoke: passed;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused tests prove:

- balanced double-entry posting;
- cross-Ledger rejection;
- immutable JournalEntry/JournalLine history;
- reversal restoration;
- correction provenance;
- integer minor-unit parsing/formatting;
- Personal Context privacy;
- friendly Accounting UI without debit/credit language;
- Bob's opening/expense/income/transfer arithmetic;
- day/month/year summaries;
- Timeline accounting projection.

## Deferred

- shared/Relationship ledgers;
- Financial Obligation → Accounting posting bridge;
- invoices/receivables/payables;
- settlement/payment reconciliation;
- exchange-rate accounting;
- tax treatment;
- budgeting/forecasting;
- bank import/reconciliation;
- richer corrections UI;
- local/browser/mobile/RTL/accessibility cumulative acceptance.

## Exit gate

Phase 13 exits because normal users can record everyday personal money through plain actions, while the underlying Ledger remains balanced, immutable, reversible/correctable and mathematically reconcilable.

Next: **Phase 14 — Proposal + Negotiation**.
