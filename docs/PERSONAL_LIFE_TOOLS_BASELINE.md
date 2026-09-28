# Personal Life Tools Baseline

## Purpose

This milestone adds three optional personal-life capabilities without widening the product into a financial dashboard or a generic secrets platform:

1. configurable Calendar cell presentation;
2. paper-like Personal Money recording;
3. a small encrypted Private Vault.

They are deliberately independent from Planner execution truth, Calculator logic, Contracts, external banking, and shared financial obligations.

## Calendar display tools

The fractal Calendar remains a projection over Planner truth.

The Planning Studio Tools rail is intentionally icon-only and narrow. Calendar display preferences control presentation only and do not create new domain records.

Current toggles:

- weekday names;
- month names;
- item counts;
- up to two compact Plan titles per cell.

Defaults remain quiet:

- weekday names off;
- month names on;
- counts on;
- Plan titles off.

The selected configuration is represented in the URL so it can be bookmarked without adding another persistence model.

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

1. Toggle weekday names, month names, counts, and Plan titles on Calendar cells.
2. Confirm the Tools rail stays icon-only and consumes minimal Calendar width.
3. Create a USD/EUR/IRR money notebook.
4. Add labels such as Cash and Visa •1234.
5. Record an expense, an income, and a transfer and inspect the transaction table.
6. Add a future purchase or earning intention without any progress calculation.
7. Add a Vault login record and confirm the secret is hidden by default.
8. Reveal/hide it and confirm another User cannot access it.
9. Confirm Profile calendar/timezone formatting remains authoritative for dates.
