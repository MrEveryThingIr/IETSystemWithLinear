# Canonical Transaction Pipeline

## Purpose

IET has accumulated many strong domain capabilities. The next product phase is not to add another parallel workflow; it is to make the existing capabilities read as one simple human journey.

This document defines the canonical transaction path for ordinary users.

## User-facing pipeline

```text
Need / Offer market
    ↓
Respond / connect
    ↓
Deal room
    ↓
Negotiate terms
    ↓
Accepted Proposal
    ↓
Versioned Contract
    ↓
Derived Commitment
    ↓
Scheduled Plan occurrence(s)
    ↓
Fulfillment assertion + evidence
    ↓
Counterparty review
    ↓
Financial Obligation
    ↓
Settlement / IET transfer
    ↓
Closed successful cycle + reputation evidence
```

The user-facing stages are deliberately simpler:

```text
Connect → Negotiate → Agree → Work → Review → Settle
```

## Domain meanings

### Need / Offer

An `ActorProfileIntent` is public market intent.

- Need: “I want this resolved / obtained.”
- Offer: “I can provide / resolve this.”
- Service is a subject/arrangement of a Need or Offer, not a third competing lifecycle.

The Intent directory is the sea/pool from which users discover counterparties.

### Relationship

A Relationship is the durable private coordination boundary created when actors decide to engage.

In product language it is the **Deal room**.

It is not itself an economic agreement. It owns the shared Context used for conversation, evidence and negotiation history.

Users should normally reach it by responding to a Need/Offer. Direct Relationship creation remains a compatibility/advanced capability, not the promoted market journey.

### Proposal

A Proposal is a versioned negotiation attempt inside an active Deal.

It contains negotiable terms and explicit party decisions.

A Proposal must not be a freestanding ordinary-user starting point. Browser creation requires an active Deal and inherits the Deal participants.

### Contract

A Contract is the authoritative accepted agreement.

Ordinary browser creation requires an accepted Proposal. Direct-contract Actions may remain for trusted imports/system/admin workflows, but the product pipeline does not advertise them.

ContractVersion is the binding versioned truth.

### Agreement

The word “Agreement” is reserved in current architecture for governance agreements such as Group membership/space rules.

Commercial exchange is represented by Contract.

UI copy must avoid using Group Agreement and transaction Contract as interchangeable concepts.

### Commitment

A Commitment is an immutable machine-readable operational obligation derived from an active ContractVersion.

It is not normally created manually by the user.

### Plan

A Plan is the temporal/execution projection of a Commitment.

The Contract/Commitment remains the authoritative source of what was agreed; Planner owns when/how execution is tracked.

Ad-hoc personal Plans may still exist, but they do not create payment entitlement unless connected to an accepted economic workflow.

### Fulfillment

A Fulfillment is the worker/provider assertion:

> I performed this agreed unit of work.

It records actual execution facts and references Assets / Content evidence.

### Financial Obligation

A Financial Obligation is created only when contract logic and accepted Fulfillment establish that value is owed.

It is not merely a note, estimate, or Planner expense.

### Settlement

Settlement records actual discharge of a Financial Obligation.

For IET, confirmation must use the guarded IET ledger/settlement rail.

---

# UnitTask

A reusable **UnitTask** is the next missing canonical concept.

It must define only the pure work unit:

- title and semantic Concept;
- action definition;
- completion/acceptance criteria;
- rules and constraints;
- prerequisites;
- expected evidence requirements;
- quantity/unit semantics;
- optional recommended evidence Content Blueprint.

It must **not** contain:

- a specific employer/customer;
- a specific worker/provider;
- binding price;
- payment method;
- scheduled date/time;
- Contract-specific quantity;
- settlement cycle.

Those belong to the Deal/Contract instance.

Recommended shape:

```text
UnitTask
  └─ UnitTaskVersion (immutable once published)
       ├─ action definition
       ├─ prerequisites
       ├─ acceptance criteria
       ├─ evidence blueprint
       └─ unit semantics

ContractServiceTerm
  ├─ unit_task_version_id
  ├─ employer / worker
  ├─ quantity
  ├─ rate / monetary unit
  ├─ payment/settlement policy
  └─ schedule policy
```

This avoids copying “inspect one machined part” into every Contract while keeping price/time mutable per deal.

---

# Evidence and Content

Planner must not grow a second document/content system.

The preferred integration is:

```text
Plan occurrence
    ↓
Quick “Done / add evidence”
    ↓
Create or reuse evidence Content from the UnitTask evidence blueprint
    ↓
Attach files/images/voice/text in normal Content
    ↓
Fulfillment references the immutable evidence revision
    ↓
Counterparty reviews evidence and discusses it in the Deal Context
```

The Planner may offer a simplified evidence composer, but the resulting artifact should be ordinary IET Content/Asset truth.

This gives fast entry without duplicating Content architecture.

---

# Review and payment

The default human flow after a Fulfillment is:

1. provider submits work + evidence;
2. counterparty opens the assertion/evidence;
3. counterparty may discuss/request clarification;
4. counterparty accepts;
5. Financial Obligation is recognized from Contract terms;
6. payer is offered **Accept & settle now** when sufficient available balance exists.

Automatic settlement may later be enabled only when the Contract version explicitly preauthorizes it. It should never arise merely from completing a Planner item.

---

# Restricted / earned IET

“System gives a user value that can be spent internally but cannot yet be cashed out” must not be implemented as an unexplained mutable balance.

It should be represented as ledger-backed IET plus an auditable withdrawal restriction/vesting condition.

A future policy can state, for example:

- internally spendable immediately;
- cash-out restricted until N accepted Fulfillments;
- restriction released as accepted work accumulates;
- default/dispute may pause release.

Available-for-spend and available-for-cashout are therefore distinct calculations over the same immutable ledger history.

---

# Product navigation rule

Ordinary users should not need to understand every internal record type to begin.

Primary economic navigation should converge toward:

- Needs & Offers
- Deals
- Planning
- Money

Proposal, Contract, Commitment, Fulfillment, Obligation and Settlement remain visible **inside the relevant Deal**, where their meaning is obvious from context.

Standalone history pages may remain for audit/search but must not advertise “Create new …” actions that bypass the pipeline.

---

# End-to-end review order

We will validate and improve the product in this order:

1. **Market** — create Need/Offer, search, matching, visibility.
2. **Deal connection** — respond to a real Intent and accept the Deal.
3. **Negotiation** — create/revise/accept Proposal inside the Deal.
4. **UnitTask** — introduce reusable pure task definitions.
5. **Contract** — bind UnitTask + parties + quantity + price + payment + schedule.
6. **Planning** — verify generated Commitment and Plan.
7. **Evidence** — complete occurrence with quick Content-backed evidence.
8. **Review** — clarification/rejection/acceptance around Fulfillment evidence.
9. **Money** — obligation, IET settlement, wallet clarity.
10. **Restricted credit & reputation** — vesting/cash-out rules based on accepted cycles.
11. **Closure** — Deal history, analytics, reputation and repeat transaction.

At each stage we populate a real scenario in the browser, inspect database/domain effects, fix problems, and only then advance.
