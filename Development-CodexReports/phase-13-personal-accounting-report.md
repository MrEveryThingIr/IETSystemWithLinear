# Phase 13 Closure — Personal Accounting v1

## Result

Phase 13 is remotely complete.

- Branch: `feat/ideal-v1-13-personal-accounting`
- Baseline: `6769b8632084f33a464145ae7501f2a4e88f5531`
- Kernel checkpoint: `81f66c6934c39cbaad26d5363deaad9239777a75` / CI `36041431006` — 465 tests / 2669 assertions
- Final runtime checkpoint: `c5a45f7d4178637e322855e71318709610e835b8` / CI `36042662030` — 469 tests / 2714 assertions
- Documentation/manual closure checkpoint: `a05210cf5c823fcd3641f6cebab0bd47e57d94bc` / CI `36043321194` — 469 tests / 2718 assertions
- The closure gate adds System Manual/contextual-help assertions; runtime architecture remains the validated `c5a45f7` checkpoint.
- Local/browser/mobile/RTL/accessibility acceptance: deferred cumulatively

## Product result

IET now exposes Personal Accounting through four plain actions: Opening balance, Add expense, Add income and Transfer.

Users see Account balances, period Income/Expense/Net summaries and immutable activity history without needing debit/credit vocabulary.

## Kernel result

The same product actions post into a double-entry kernel:

- MonetaryUnit;
- Ledger;
- Account;
- JournalEntry;
- JournalLine.

Amounts are integer minor units, entries must balance before commit, and Accounts cannot cross Ledger boundaries.

## Immutability result

Posted JournalEntry/JournalLine history cannot be edited or deleted.

Reversal posts equal-and-opposite lines while preserving the original.
Correction preserves the original, posts a reversal and posts a replacement linked back to the original.

## Privacy result

Phase 13 Ledger authority is Personal Context only.

An unrelated Actor cannot select, view, manage or post into another Actor's Ledger.

Shared/Relationship financial authority remains deferred to the explicit Financial Obligation + Settlement bridge.

## Story result

Bob's EUR story is remotely executable:

- opening Cash EUR 1000.00;
- expense EUR 25.00;
- income EUR 100.00;
- transfer EUR 200.00 Cash → Bank;
- derived Cash EUR 875.00;
- Bank EUR 200.00;
- total assets EUR 1075.00;
- monthly Income EUR 100.00;
- Expense EUR 25.00;
- Net EUR 75.00.

## Timeline result

JournalEntry source records project into the unified Context Timeline and link back to Accounting.

Timeline remains read-only projection code; JournalEntry/JournalLine remain authority.

## Validation

Final CI `36042662030` is green:

- 469 tests / 2714 assertions;
- PHPStan clean;
- Pint clean;
- Vite green;
- migration rollback/reapply green;
- scheduler/database-queue/backup smoke green;
- npm audit 0 vulnerabilities;
- Composer audit no advisories.

## Next

Phase 14 — Proposal + Negotiation.
