# Phase 17 — Financial Obligation + Settlement bridge

## Status

Remote runtime implementation is complete and green on `feat/ideal-v1-17-financial-obligation-settlement`.

Kernel checkpoint:

~~~text
SHA: 90e0e0ec0033954bacdaca944fee9a2efe2e9095
GitHub Actions: 36114145008
500 tests / 3036 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Final runtime/UI checkpoint:

~~~text
SHA: 47bae48638345a807623ceb7f1ae44866d09d9e6
GitHub Actions: 36115933156
502 tests / 3065 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Documentation/manual closure checkpoint:

~~~text
SHA: a24c1c3a3a34290c15e8d3d5cb633df22d5bead9
GitHub Actions: 36116508652
502 tests / 3069 assertions
System Manual: 22 chapters
Contextual Help: Financial Obligation routes mapped
All automated gates: green
~~~

Baseline: Phase 16 integration merge `d4289f46d4726d5fc5a581abb43ce6a1efc6dfc8`.

## Purpose

Turn explicitly accepted performance into explicit economic consequences without mutating a magic balance or collapsing Contract, Fulfillment, Accounting and payment into one state.

The authoritative chain is:

~~~text
accepted ContractVersion
→ Commitment
→ accepted Fulfillment
→ Financial Obligation
→ explicit Accounting posting
→ JournalEntry
→ Settlement claim
→ counterparty confirmation
→ explicit Settlement Accounting
~~~

## Implemented kernel

Phase 17 adds:

- immutable `FinancialObligation`;
- immutable/lifecycle-controlled `Settlement`;
- immutable `FinancialObligationEvent`;
- obligation recognition from currently accepted Fulfillment;
- bilateral debtor/creditor authorization;
- explicit per-Actor Accounting posting;
- idempotent JournalEntry source provenance;
- Settlement proposal + counterparty confirmation/rejection;
- Settlement Accounting posting;
- Contract-level derived financial summary;
- financial source events in the existing Contract Context Timeline.

## Economic trigger

Accepted Fulfillment is required before a Financial Obligation may be recognized.

The current paid-work composition maps:

- Commitment obligor = work performer / creditor;
- Commitment beneficiary = receiving party / debtor.

The beneficiary/debtor explicitly recognizes the amount and monetary unit after Fulfillment has been accepted.

Fulfillment submission alone does not create money. Fulfillment acceptance alone does not create money. Financial Obligation recognition is a separate authoritative Action.

One Fulfillment may create at most one Financial Obligation.

## Immutable obligation truth

Financial Obligation records:

- exact source Fulfillment;
- exact governing ContractVersion;
- debtor Actor;
- creditor Actor;
- MonetaryUnit;
- integer minor-unit amount;
- optional due time / description;
- recognizing Actor / time.

The obligation is immutable after recognition.

Its paid/outstanding state is derived from confirmed Settlements; no balance column is incremented/decremented directly.

## Accounting bridge

Each bilateral party may explicitly post the obligation into **their own Personal Ledger**.

The posting Action:

- creates/uses the party's own Ledger in the obligation MonetaryUnit;
- creates the appropriate receivable/payable and income/expense bridge Accounts;
- posts one balanced JournalEntry;
- records source_type/source_uuid/acting_user provenance;
- is idempotent for obligation + Actor.

The debtor and creditor receive separate Personal-Ledger JournalEntries. Neither Actor writes into the other Actor's Ledger.

Accounting remains personal accounting truth; Financial Obligation remains shared economic-source truth.

## Settlement truth

Either bilateral party may propose a Settlement claim up to the currently outstanding amount.

A Settlement records:

- exact Financial Obligation;
- immutable amount;
- paid time;
- optional method/reference/note;
- proposing Actor;
- Pending Confirmation lifecycle.

The proposer cannot confirm their own claim.

The counterparty explicitly confirms or rejects it.

A Settlement cannot be confirmed while the source Fulfillment is disputed/not economically accepted.

Confirmed Settlements contribute to derived paid/outstanding values.

Rejected Settlement history remains durable.

## Settlement Accounting

After a Settlement is confirmed, each party may explicitly post it into their own Personal Ledger.

Settlement Accounting is idempotent for Settlement + Actor and requires that Actor to have already posted the source Financial Obligation recognition into that Ledger.

No Settlement confirmation silently changes Accounting.

## Derived financial state

`ContractFinancialSummary` derives, per Contract + MonetaryUnit and optionally per Actor:

- scheduled work count;
- worked count;
- accepted count;
- disputed count;
- earned amount;
- paid amount;
- outstanding amount;
- disputed amount;
- obligation count;
- confirmed Settlement count.

Example with three accepted Riverside workdays at 1,500,000 IRR each:

~~~text
earned      4,500,000
paid        3,000,000
outstanding 1,500,000
disputed            0
~~~

If the third accepted Fulfillment becomes disputed:

~~~text
earned      3,000,000
paid        3,000,000
outstanding         0
disputed    1,500,000
~~~

Resolving the dispute back to accepted restores that amount to outstanding without rewriting the Financial Obligation.

## Privacy boundary

Financial details are bilateral.

In a three-party Contract, a third Contract party who is neither debtor nor creditor of a specific Financial Obligation:

- may still view the Contract if authorized;
- cannot view that bilateral Financial Obligation;
- does not see its financial Timeline events;
- cannot post its Accounting;
- cannot propose/respond to its Settlement.

## Product experience

The user-facing flow is wired into existing surfaces:

- accepted Fulfillment → **Recognize financial obligation**;
- Contract page → derived earned/paid/outstanding/disputed summary;
- Financial Obligation page → details, Accounting posting, Settlement proposal/response, Settlement Accounting;
- Contract Context Timeline → authorized source-linked financial events.

The UI uses MonetaryUnit precision and human money formatting while persistence remains integer minor units.

## Story proof — Riverside

Alice and Bob have an Active Riverside ContractVersion and three accepted workday Fulfillments.

Alice, the beneficiary/debtor, explicitly recognizes three 1,500,000 IRR Financial Obligations.

Both Alice and Bob explicitly post each obligation to their own Accounting.

Alice records a 3,000,000 IRR payment Settlement claim.

Bob explicitly confirms it.

Alice and Bob each explicitly post the confirmed Settlement to their own Personal Ledger.

The Contract summary derives:

- scheduled/worked/accepted counts from source domains;
- earned = 4,500,000 IRR;
- paid = 3,000,000 IRR;
- outstanding = 1,500,000 IRR.

No magic balance is stored.

## Timeline composition

FinancialObligation/Settlement events project into the existing Contract Context Timeline.

The viewer must also be authorized to view that bilateral Financial Obligation.

Timeline stores no duplicate financial authority and links back to the Financial Obligation source page.

## Authority boundary

Phase 17 does **not** infer payment from:

- Conversation text;
- Content prose;
- Planner completion;
- Fulfillment submission;
- Fulfillment acceptance alone;
- Contract activation alone;
- a JournalEntry written independently of the bridge.

Financial Obligation, Accounting posting, Settlement confirmation and Settlement Accounting are distinct explicit facts.

Settlement is payment-record truth inside IET; it does not claim external-bank verification unless a later bank/reconciliation source explicitly proves that.

## Remote validation

Final runtime CI `36115933156` proves:

- PHPUnit: **502 passed / 3065 assertions**;
- PHPStan: no errors;
- Pint: clean;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler/database-queue smoke: passed;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused tests prove:

- no obligation before accepted Fulfillment;
- one obligation per Fulfillment;
- exact ContractVersion/Fulfillment provenance;
- balanced per-Actor obligation Accounting;
- Accounting posting idempotency;
- Settlement claim immutability;
- counterparty-only Settlement confirmation;
- no over-settlement;
- dispute blocks Settlement confirmation;
- derived earned/paid/outstanding/disputed values;
- dispute/resolution changes derivation without rewriting obligation;
- balanced per-Actor Settlement Accounting;
- outsider/bilateral privacy;
- Contract summary + financial Timeline composition.

## Deferred

- bank import / external payment verification;
- invoices/document generation;
- receivable/payable aging;
- recurring billing;
- tax handling;
- exchange-rate accounting;
- refunds/chargebacks beyond current explicit Settlement history;
- multi-party split obligations;
- domain-blueprint-specific financial terminology;
- local/browser/mobile/RTL/accessibility cumulative acceptance.

## Exit gate

Phase 17 exits because authoritative accepted performance may now create an explicit immutable financial consequence, be posted explicitly into each party's own Accounting, be settled only by counterparty-confirmed payment records, and be summarized entirely from source facts without mutating a magic balance.

Next: **Phase 18 — Journey / Relationship / Domain Blueprints**.
