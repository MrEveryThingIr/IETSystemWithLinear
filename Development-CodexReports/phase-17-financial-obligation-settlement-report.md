# Phase 17 Closure — Financial Obligation + Settlement bridge

## Result

Phase 17 runtime is remotely complete.

- Branch: `feat/ideal-v1-17-financial-obligation-settlement`
- Baseline: `d4289f46d4726d5fc5a581abb43ce6a1efc6dfc8`
- Kernel checkpoint: `90e0e0ec0033954bacdaca944fee9a2efe2e9095` / CI `36114145008` — 500 tests / 3036 assertions
- Final runtime/UI checkpoint: `47bae48638345a807623ceb7f1ae44866d09d9e6` / CI `36115933156` — 502 tests / 3065 assertions
- Documentation/manual closure: pending closure gate
- Local/browser/mobile/RTL/accessibility acceptance: deferred cumulatively

## Product result

IET now connects accepted work to money through explicit authoritative steps rather than a mutable balance.

Accepted Fulfillment may be explicitly recognized as a bilateral Financial Obligation. Each party may explicitly post that source fact to their own Personal Accounting. A payment claim becomes Settlement only after counterparty confirmation, and each party separately posts Settlement Accounting.

## Source-chain result

The durable chain is:

ContractVersion → Commitment → accepted Fulfillment → Financial Obligation → per-Actor JournalEntry → confirmed Settlement → per-Actor Settlement JournalEntry.

Every bridge retains exact source UUID/provenance.

## Derivation result

ContractFinancialSummary derives scheduled/worked/accepted/disputed work plus earned/paid/outstanding/disputed financial values.

No stored “balance owed” field is mutated.

## Privacy result

Financial Obligation visibility is bilateral even when the surrounding Contract has additional parties.

An unrelated Actor or uninvolved third Contract party does not gain financial access from Contract visibility alone.

## Dispute result

A disputed source Fulfillment blocks Settlement confirmation.

The disputed amount moves out of earned/outstanding derivation and into disputed derivation until explicit dispute resolution.

The Financial Obligation itself remains immutable.

## Accounting result

Debtor and creditor each post balanced entries only to their own Personal Ledger.

Retries are idempotent by source + Actor.

Settlement Accounting requires prior obligation recognition posting and confirmed Settlement state.

## Validation

Runtime/UI CI `36115933156` is green:

- 502 tests / 3065 assertions;
- PHPStan clean;
- Pint clean;
- Vite green;
- migration rollback/reapply green;
- scheduler/database-queue/backup smoke green;
- npm audit 0 vulnerabilities;
- Composer audit no advisories.

## Next

Phase 18 — Journey / Relationship / Domain Blueprints.
