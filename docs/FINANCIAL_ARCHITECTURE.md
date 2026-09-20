# IET Financial Architecture

## Purpose

IET separates three concerns that must not be conflated:

1. business/economic events such as work, invoices, expenses, commitments, fulfillment, settlements, and payments;
2. production accounting, which records financial truth through an immutable balanced ledger;
3. the Financial Laboratory, which experiments with internal value instruments, valuation models, issuance rules, and simulated redemption.

This separation allows experimentation without weakening accounting correctness or implying that every experimental token is real money.

## 1. Production Accounting Kernel

### Principles

The accounting kernel should be deliberately conservative.

Rules:

- journal posting is atomic;
- every posted Journal Entry balances;
- posted entries are immutable;
- errors are corrected by reversal/correcting entries;
- source domain records explain why accounting exists;
- idempotency prevents duplicate posting;
- history is never cascade-deleted;
- cached balances are derived data, not the source of truth;
- money calculations never use floating point;
- acting Actor and acting User provenance are preserved;
- cross-currency/accounting-rate evidence is immutable at posting time.

## 2. Target production model

### MonetaryUnit

Represents accounting-denominated monetary units.

Suggested properties:

- uuid;
- code;
- name;
- kind;
- precision;
- status.

Kinds may initially include:

- fiat_currency;
- fixed_denomination.

Do not automatically put internal reward points or experimental Group instruments here.

Example:

~~~text
IRR
    kind = fiat_currency

TOMAN
    kind = fixed_denomination
    base = IRR
    fixed ratio = 10
~~~

### ExchangeRate

Historical rate observation.

Suggested fields:

- from_unit_id;
- to_unit_id;
- rate;
- observed_at;
- source;
- provider_reference;
- metadata.

Floating exchange rates must not be represented by one permanent multiplier.

### Ledger

Accounting book.

Suggested fields:

- uuid;
- owner_actor_id;
- context_type/context_id nullable;
- name;
- reporting_unit_id nullable;
- status;
- created_by_actor_id;
- timestamps.

Examples:

- personal ledger;
- company ledger;
- project/subledger context where appropriate.

A Group is not automatically an accounting/legal entity.

### Account

Belongs to a Ledger.

Separate accounting classification from operational purpose.

Accounting class:

- asset;
- liability;
- equity;
- revenue;
- expense.

Possible purpose:

- cash;
- bank;
- wallet;
- accounts_receivable;
- accounts_payable;
- escrow_cash;
- escrow_liability;
- wage_expense;
- travel_expense;
- fee_revenue;
- other domain-specific safe values.

Suggested fields:

- uuid;
- ledger_id;
- parent_account_id nullable;
- code;
- name;
- class;
- normal_balance;
- purpose nullable;
- monetary_unit_id nullable;
- owner_actor_id nullable;
- context_type/context_id nullable;
- allow_negative;
- status;
- timestamps.

Close/archive accounts. Do not delete financially referenced accounts.

### JournalEntry

Represents one accounting event.

Suggested fields:

- uuid;
- ledger_id;
- status;
- description;
- effective_at;
- posted_at;
- reversal_of_id nullable;
- source_type/source_id nullable;
- initiated_by_actor_id nullable;
- acting_user_id nullable;
- idempotency_key nullable;
- metadata;
- timestamps.

Recommended lifecycle:

~~~text
draft → posted
draft → voided
posted → reversed through a separate reversal entry
~~~

Do not use payment-processing states such as processing/failed/cancelled as the core posted-ledger lifecycle.

### JournalLine

Suggested fields:

- journal_entry_id;
- line_number;
- account_id;
- monetary_unit_id;
- direction: debit / credit;
- amount;
- exchange_rate nullable;
- reporting_amount nullable;
- memo nullable;
- metadata;
- timestamps.

Invariant:

~~~text
sum(debit reporting amounts) = sum(credit reporting amounts)
~~~

For single-unit entries, debit amount equals credit amount in the same unit.

Use:

~~~text
unique(journal_entry_id, line_number)
~~~

Do not prohibit multiple lines against the same account/direction.

### Balance cache

Optional derived table/cache:

- account_id;
- monetary_unit_id;
- balance;
- version;
- updated_through_line_id;
- updated_at.

Periodic balance snapshots may be added later for reporting/performance.

Journal Lines remain source of truth.

## 3. Source-domain adapters

Business domains should not construct arbitrary ledger rows from UI components.

Use explicit accounting Actions/policies.

Examples:

~~~text
Expense approved
→ PostExpenseAccounting

Payroll obligation recognized
→ PostPayrollAccrual

Payment confirmed
→ PostPaymentSettlement
~~~

Source objects may include:

- Expense;
- Invoice;
- Payment;
- Obligation;
- Payroll;
- Commitment Fulfillment;
- Settlement;
- Refund.

The accounting kernel should not need a type enum containing every business event.

## 4. Examples

### Personal cash expense

User records €30 restaurant expense.

Accounting:

~~~text
Food Expense    Debit  30 EUR
Cash/Bank       Credit 30 EUR
~~~

The UX says "Add expense", not "Create Journal Entry".

### Worker salary

When €800 compensation becomes owed:

~~~text
Wage Expense    Debit  800 EUR
Wages Payable   Credit 800 EUR
~~~

When paid:

~~~text
Wages Payable   Debit  800 EUR
Bank            Credit 800 EUR
~~~

Work fulfillment, payroll obligation, and payment are distinct domain facts.

### Restaurant payable

When accepted meal service worth €180 is recognized:

~~~text
Meal Expense             Debit  180 EUR
Maria Accounts Payable   Credit 180 EUR
~~~

When paid:

~~~text
Maria Accounts Payable   Debit  180 EUR
Bank                     Credit 180 EUR
~~~

## 5. Escrow / custody

Do not model real custody as one magical "escrow wallet" account.

If IET ever holds funds belonging to another party, accounting may need both:

- custodial cash asset;
- corresponding liability owed to the owner.

Real custody/escrow may introduce regulatory and provider requirements. Treat it as a controlled production capability.

## 6. Legacy financial migration mapping

Legacy currencies:
- keep code/name/precision ideas;
- fixed multiplier only for mathematically fixed denomination relationships;
- use ExchangeRate for market rates;
- separate experimental instruments from MonetaryUnit.

Legacy accounts:
- replace user_id ownership with Ledger/Actor semantics;
- replace wallet/escrow/treasury/fee/reward/system type enum with accounting class + purpose;
- keep the idea that balance is derived;
- preserve optimistic/concurrency concerns through posting locks/versioning;
- do not cascade-delete accounting history.

Legacy transactions:
- accounting meaning becomes JournalEntry;
- remove business-specific type enum;
- preserve UUID;
- preserve idempotency;
- preserve source/reference concept;
- preserve Actor/User provenance in improved form;
- posting atomicity is mandatory, not optional.

Legacy ledger_entries:
- rename conceptually to JournalLine;
- preserve positive amount + debit/credit direction;
- remove balance_before/balance_after from every line;
- remove unique(transaction, account, direction);
- use ordered line numbers;
- restrict deletion.

## 7. Financial Laboratory

### Purpose

The laboratory is for experiments in internal value systems without pretending experimental values are production money.

It may explore:

- internal credits;
- Group units;
- reward systems;
- vouchers;
- indexed units;
- profit-participation experiments;
- operational-cycle valuation;
- simulated reserve/redemption;
- alternative settlement rules.

### FinancialInstrument

Generic experimental instrument identity.

Suggested kinds:

- internal_credit;
- loyalty_point;
- voucher;
- group_unit;
- commodity_indexed_unit;
- redeemable_claim;
- profit_participation_unit;
- experimental_asset.

"Coin" may be a product label; the domain abstraction should be FinancialInstrument.

### Versioned policy

Each instrument version may reference:

- IssuancePolicy;
- TransferPolicy;
- ValuationPolicy;
- RedemptionPolicy;
- ReservePolicy;
- DistributionPolicy.

Policies are immutable once active for historical interpretation.

### Instrument events

Preserve append-only events such as:

- issued;
- transferred;
- burned;
- distributed;
- valuation_observed;
- reserve_observed;
- redemption_requested;
- simulated_redemption;
- redeemed where production approval exists.

### Valuation models

An experimental valuation model may use inputs such as:

- reserve value;
- verified revenue;
- completed operational cycles;
- unresolved obligations;
- active demand;
- supply;
- customer satisfaction;
- validated reputation;
- fulfillment quality.

Rules:

- no arbitrary executable PHP/JS from DB;
- use trusted model registry or a constrained expression language;
- formula/model version is immutable;
- exact input snapshot is stored;
- output is timestamped;
- model result is a reference valuation, not automatically accounting income or a promise of redemption.

Example:

~~~text
Instrument: BUILD-X
ValuationModel: v3
Inputs:
    reserve = ...
    completed_cycles = ...
    customer_satisfaction = ...
    supply = ...
Result:
    reference value = 1.283 USD
~~~

## 8. Operational-cycle laboratory

The "electrical circuit" analogy may be useful as an analytical model, not a physical conservation law.

A future OperationalCycle may capture:

- participants;
- Needs/Offers;
- commitments;
- inputs;
- activities;
- outputs;
- fulfillment;
- customer outcomes;
- money/value flows;
- reputation/quality metrics;
- cycle duration;
- unresolved obligations.

Researchers may test relationships between cycle metrics and experimental instrument valuation.

Do not claim economic value is mathematically conserved unless a specific model proves a defined invariant.

## 9. Value change is not salary

If an instrument reference value rises 28%, a holder may have an unrealized valuation gain.

That is not automatically salary or realized income.

Keep separate:

- compensation commitment;
- distribution event;
- instrument valuation;
- accounting recognition;
- cash payment/redemption.

This distinction must remain explicit in code and UI.

## 10. Production modes

### Laboratory mode

Allowed:

- freely create experimental instruments;
- simulated balances;
- simulated transfers;
- simulated valuations;
- simulated redemptions;
- research metrics.

No external monetary promise.

### Closed-loop production mode

Potentially allowed after review:

- restricted internal credits;
- defined use/transfer rules;
- no implicit cash redemption;
- explicit terms.

### External redeemable mode

Only for specifically approved instruments/use cases.

Requires:

- reserve/redemption specification;
- payment/banking provider;
- reconciliation;
- payout lifecycle;
- fraud/abuse controls;
- audit;
- jurisdiction-specific legal/compliance review;
- operational limits;
- monitoring.

A Group must not be able to flip an arbitrary experimental instrument into cash-redeemable mode.

## 11. External payments

Target domain separates:

~~~text
PaymentIntent / PayoutRequest
→ ProviderAttempt
→ ProviderConfirmation
→ Reconciliation
→ Accounting Journal
~~~

Payment provider status is not the ledger.

Webhook processing must be idempotent.

No external payment success is considered reconciled until provider evidence and accounting expectations match.

## 12. Security / integrity

Financial domains require:

- authorization and acting-Actor verification;
- row locking during posting;
- idempotency;
- immutable posted evidence;
- strict decimal precision;
- no floating point;
- audit events;
- rate limits;
- replay protection;
- provider-signature verification;
- separation of test/lab and production credentials;
- reconciliation tooling;
- privileged-operation review;
- retention and backup.

## 13. Phase acceptance proofs

### Accounting Kernel proof

Before considered stable:

1. personal expense posts a balanced entry;
2. payroll accrual/payment posts correct two-stage accounting;
3. restaurant payable/payment works with same kernel;
4. duplicate source idempotency does not double-post;
5. concurrent posting preserves balances;
6. reversal preserves original entry;
7. account/history cannot be deleted destructively;
8. multi-currency evidence stores exact rate used.

### Financial Laboratory proof

Before production-money integration:

1. Group can create an experimental instrument;
2. instrument version/policy is immutable after activation;
3. issuance/transfer/burn history is reproducible;
4. valuation model stores exact model/input/output snapshots;
5. valuation changes do not create salary/accounting entries automatically;
6. simulated redemption cannot trigger external money movement;
7. permissions isolate instruments across Groups/Actors;
8. experiment can be deleted/archived only without corrupting evidence.

## 14. Hard boundary

The Accounting Kernel records financial truth.

The Financial Laboratory tests hypotheses about value.

Controlled payment infrastructure moves external money.

These three systems may integrate, but they must never be collapsed into one mutable "balance/coin" subsystem.
