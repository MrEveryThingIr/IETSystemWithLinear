# Personal Life Tools Baseline

## Purpose

This milestone adds four focused personal-life improvements without widening the product into a financial dashboard or a generic secrets platform:

1. explicit Planner attention semantics;
2. configurable interval-aware Calendar presentation;
3. paper-like Personal Money recording;
4. a small encrypted Private Vault.

They are deliberately independent from Planner execution truth, Calculator logic, Contracts, external banking, and shared financial obligations.

## Planner attention semantics

A fixed-time Plan now states whether its interval consumes foreground attention:

- **Needs full focus** (`exclusive`) — another full-focus baseline Plan may not overlap the interval.
- **Can run in the background** (`background`) — may overlap foreground work, such as listening to English audio while working.

The safe default is full focus. Background compatibility is explicit rather than inferred from category or title.

The invariant is enforced twice:

- create/edit performs a friendly preflight validation;
- Schedule Rule creation serializes focused scheduling per Context and rechecks after occurrence materialization, so concurrent saves cannot silently create two overlapping full-focus intervals.

Flexible-day Plans still express day-level intent and therefore do not participate in exact interval collision blocking.

## Calendar display tools

The fractal Calendar remains a projection over Planner truth.

The Planning Studio Tools rail is intentionally icon-only and narrow. Calendar display preferences control presentation only and do not create new domain records.

Current controls:

- weekday names;
- month names;
- item counts;
- up to two compact Plan titles per cell;
- cell mode: details or visual map;
- color mapping: none, Plan, category, or attention mode.

Defaults remain quiet:

- weekday names off;
- month names on;
- counts on;
- Plan titles off;
- detail mode;
- no color mapping.

The selected configuration is represented in the URL so it can be bookmarked without adding another persistence model.

Calendar occupancy uses interval overlap, not only an occurrence start timestamp. A 07:00–07:30 Plan therefore occupies both 07:00–07:15 and 07:15–07:30 at 15-minute resolution, while 07:30 is free again.

Plan-color mapping follows repeat provenance. Copies produced by Repeat time window retain one root Plan identity, so a 21-day repeated pattern can render as one coherent color across days. Category colors are namespaced by Context, allowing the same category vocabulary to be mapped independently across different Contexts. Attention mapping uses distinct focused/background colors.

The visual-map path is intentionally text-light: counts may remain visible, while titles can stay hidden and color stripes carry the pattern.

## Personal Money

The baseline Money surface reuses the mature Personal Accounting kernel:

- MonetaryUnit;
- Ledger;
- Account;
- immutable JournalEntry / JournalLine;
- expense;
- income;
- transfer;
- reversal.

The human-facing baseline intentionally does not expose debit/credit terminology, balance dashboards, period summaries, forecasts, budgeting formulas, or a calculator.

### Accounts and cards

Cash, bank accounts, cards, and wallets are represented as user-named Asset accounts.

Examples:

- Cash;
- Main bank;
- Visa •1234.

No bank connection exists. The UI explicitly recommends a nickname or last four digits rather than storing a full payment-card number.

### Transactions

The transaction table records:

- date;
- kind;
- description;
- from → to;
- exact amount and monetary unit.

Income is presented with a positive sign and expense with a negative sign. Transfer is neutral because it moves money between the user's own accounts.

Posted journal history remains immutable. A mistake is reversed rather than silently edited or deleted.

### Money intentions

A Money Intention is only a reminder:

- want to buy X for Y;
- want to earn Y;
- optional target date;
- optional notes.

It performs no savings-progress calculation, forecasting, allocation, or automatic Planner generation. Those belong to later Calculator / financial-planning capabilities.

## Private Vault

The Vault stores personal memory records such as:

- login;
- email registration;
- phone registration;
- account identifier;
- private note.

Revealable fields are encrypted at rest using Laravel encrypted casts and the application key:

- identifier;
- secret/password;
- URL;
- notes.

They are **not hashed**, because a hash is intentionally irreversible and cannot support Reveal/Hide. The user's actual IET authentication password remains separately hashed by the User model.

Vault records are scoped to the authenticated User and hidden by default. Reveal is an explicit UI action.

The Vault is a convenience memory store, not a replacement for a dedicated enterprise password manager. Backups of the application key must be protected because encrypted values depend on it.

## Explicit exclusions

This milestone does not add:

- external bank connections;
- payment execution;
- full card-number storage workflows;
- account aggregation;
- automatic balances or budgeting dashboards in the baseline UI;
- forecasting;
- interest/rate calculations;
- currency conversion;
- Calculator;
- shared debts/obligations;
- automatic financial-to-Planner coupling;
- Vault sharing.

## Acceptance scenarios

1. Create a 07:00–07:30 full-focus Plan, then confirm another full-focus Plan cannot overlap 07:15 while a background-compatible Plan can.
2. Drill to 15-minute and 1-minute Calendar cells and confirm the whole 07:00–07:30 interval is occupied, not only its start cell.
3. Toggle detail/map mode and color by Plan, category, and attention; repeat a Plan across multiple days and confirm its visual identity stays coherent.
4. Toggle weekday names, month names, counts, and Plan titles on Calendar cells.
5. Confirm the Tools rail stays icon-only and consumes minimal Calendar width.
6. Create a USD/EUR/IRR money notebook.
7. Add labels such as Cash and Visa •1234.
8. Record an expense, an income, and a transfer and inspect the transaction table.
9. Add a future purchase or earning intention without any progress calculation.
10. Add a Vault login record and confirm the secret is hidden by default.
11. Reveal/hide it and confirm another User cannot access it.
12. Confirm Profile calendar/timezone formatting remains authoritative for dates.
