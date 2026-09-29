# Phase 23 — Ideal v1 Paid-Service Financial Workflow

## Status

Implementation milestone for the first publishable end-to-end paid-service flow.

The goal is deliberately conventional and auditable:

~~~text
negotiation / proposal
→ exact ContractVersion
→ explicit acceptance
→ Contract activation
→ generated service Commitment
→ generated shared Plan or explicit custom planning
→ actual unit-job execution
→ Fulfillment + evidence
→ employer acceptance
→ deterministic Financial Obligation
→ cash Settlement claim
→ worker confirmation
→ optional per-party Accounting posting
~~~

This phase does not introduce wallets, tokens, internal currencies, custody, escrow, or external payment processing.

## Human story

Alice employs Bob for a unit-defined service.

Example:

~~~text
service: on-site work
quantity: 6 days
value: 1,500,000 IRR per accepted day
schedule: 08:00–17:00
settlement cycle: weekly
~~~

The prose terms remain sealed Content, but the economic/scheduling facts are also stored as immutable structured facts on the exact ContractVersion:

- employer;
- worker/service provider;
- service kind/title;
- total quantity;
- quantity per planned occurrence;
- unit;
- agreed monetary unit and unit rate;
- settlement cycle and payment due delay;
- contract-driven Planner schedule;
- whether the Plan and Financial Obligation are automatically derived.

Every required Contract party accepts the exact ContractVersion bundle before it becomes authoritative.

## Contract-driven planning

When an accepted structured service ContractVersion becomes active, the system idempotently creates one service Commitment governed by that exact ContractVersion.

If automatic planning is enabled, it also creates one shared Plan bound to that Commitment and materializes occurrences from the agreed schedule. If automatic planning is disabled, the Commitment still exists and the parties may create/bind the Plan manually through the ordinary Planner.

A generated Plan records scheduled intent and actual execution. It does not by itself prove that work was accepted or money became owed.

## Shared workspace and evidence

The existing Contract Context remains the shared collaboration space.

Both authorized parties can use Conversation, Content, uploaded images/assets, evidence references and Timeline.

Human-readable content and images can explain or support work, but they do not silently alter Contract, Fulfillment, obligation, Settlement, or Accounting authority.

## Unit-job completion

For a generated service Commitment:

1. the worker starts/completes the relevant Plan occurrence inside Planner lifecycle rules;
2. the worker submits Fulfillment against that completed occurrence;
3. Planner evidence is inherited by the Fulfillment;
4. the employer explicitly reviews the Fulfillment.

The fulfillment form defaults to the ContractVersion's agreed quantity-per-occurrence.

## Deterministic earned value

If structured service terms enable automatic obligation recognition, employer acceptance calculates:

~~~text
accepted quantity × agreed ContractVersion unit rate
= Financial Obligation amount
~~~

Money arithmetic uses integer minor units and the existing exact quantity scale.

The employer is the debtor. The worker is the creditor.

The calculation never asks the employer to re-enter the accepted rate.

If an automatic step needs retrying, the employer can deterministically recalculate from the same immutable ContractVersion instead of typing a new amount.

## Settlement cycles

The ContractVersion records an economic settlement cycle:

- per accepted fulfillment;
- weekly;
- monthly;
- contract/service end.

The cycle determines the due date of each earned obligation.

A cycle does not collapse multiple earned obligations into one mutable balance. Each unit-job remains traceable.

## Contract cash settlement batch

The first release supports a simple cash settlement action at Contract level.

Either side may initiate the cash statement:

- the debtor/employer may record “I paid cash”;
- the creditor/worker may record “I received cash”.

The system allocates the stated amount oldest-first across accepted outstanding obligations for the same Contract, counterparty and monetary unit.

Each allocation is still a normal Settlement row.

The non-proposing counterparty explicitly confirms or rejects the batch through its pending Settlement allocations.

A pending payment reserves its allocated value so another payment claim cannot overlap the same outstanding amount.

## Financial meanings

The Contract cockpit distinguishes:

~~~text
earned
    accepted work with recognized Financial Obligation

awaiting confirmation
    cash payment/receipt claimed by one party but not yet confirmed by the other

paid / settled
    confirmed Settlement value

outstanding
    earned minus confirmed Settlement

still payable now
    outstanding minus pending Settlement claims

disputed
    economic value whose source Fulfillment is currently disputed
~~~

No editable aggregate balance is authoritative. All totals are derived from source facts.

## Per-day traceability

The Contract financial summary groups economic state by actual work date:

~~~text
date
→ Fulfillment
→ accepted quantity
→ exact ContractVersion
→ earned amount
→ pending payment
→ confirmed payment
→ remaining amount
~~~

This supports daily work, piece work, service units, attendance units and similar first-release agreements.

## Accounting boundary

Financial Obligation and confirmed Settlement are shared economic facts.

Personal Accounting remains a separate per-Actor truth.

Each party may explicitly post Financial Obligation and confirmed Settlement into their own Personal Ledger.

One party never writes another party's accounting records.

A Contract settlement does not implicitly move external money.

## Amendment boundary

The first release intentionally blocks prose-only amendments on structured paid-service Contracts.

Changing rate, service quantity or schedule requires a future structured economic amendment flow that can safely version structured service terms, cut over schedule occurrences at the effective instant, preserve old work under the old rate and create future work under the new version.

Until that workflow exists, changed economics should use a new paid-service Contract rather than silently mutating or dropping structured terms.

## Deferred

Explicitly deferred from the publishable v1:

- alternative settlement consideration;
- skill/service barter;
- needs-based supplier recommendations;
- internal credits/tokens;
- custody/escrow;
- external payment-provider execution;
- bank reconciliation;
- structured economic amendments;
- tax/payroll jurisdiction logic;
- multi-creditor settlement batch;
- automatic posting into either party's Personal Accounting.

These remain compatible with the Financial Laboratory direction but do not weaken the ordinary cash baseline.

## Acceptance proof

The focused end-to-end test must prove:

1. structured service terms are bound to an exact ContractVersion;
2. no Commitment/Plan exists before full Contract acceptance;
3. activation always creates one service Commitment; automatic mode also creates its Plan while custom mode intentionally does not;
4. both parties can use the Contract Context;
5. occurrences reflect the agreed schedule;
6. completed occurrence can become Fulfillment;
7. employer acceptance automatically prices the unit-job from Contract terms;
8. multiple unit-jobs produce separate immutable obligations;
9. one cash settlement batch allocates oldest-first across obligations;
10. pending allocations reduce still-payable-now without pretending to be paid;
11. the non-proposing counterparty can confirm either a debtor-reported payment or creditor-reported receipt, changing pending value into confirmed paid/settled value;
12. per-day financial rows trace values to work dates;
13. no JournalEntry appears merely because economic truth was recognized/settled;
14. each party can explicitly post their own Accounting side;
15. prose-only amendment of structured service economics is rejected.

## Future laboratory bridge

This workflow becomes the baseline/control model for later Economic Laboratory experiments.

Every alternative model should be compared against the conventional flow rather than replacing it:

~~~text
cash baseline
vs.
reciprocal service commitments
vs.
needs-linked supplier settlement
vs.
other explicitly modeled experimental consideration
~~~

The laboratory may calculate scenarios and recommendations; it must not rewrite production financial truth.
