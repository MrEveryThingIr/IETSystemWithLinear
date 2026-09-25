# Phase 16 — Commitment + Fulfillment

## Status

Remote implementation complete and green on `feat/ideal-v1-16-commitment-fulfillment`.

Kernel checkpoint:

~~~text
SHA: 7a7481a271687bd307746d054749ce2599d87c4d
GitHub Actions: 36089527651
492 tests / 2964 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Final runtime/UI checkpoint:

~~~text
SHA: 67b986cce96f7d6e72db811045a9c1a01c581ddb
GitHub Actions: 36093668972
495 tests / 2989 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Baseline: Phase 15 integration merge `09c1c792baa5116645f7795887d59fecafe3fc3f`.

## Purpose

Separate **what must happen** from **what actually happened**.

Contract is agreement truth. Commitment is obligation truth. Planner is schedule/execution-time truth. Fulfillment is actual-performance truth. Review/dispute/correction records determine whether submitted performance is accepted without rewriting historical facts.

## Implemented kernel

Phase 16 adds:

- `Commitment`;
- immutable `CommitmentEvent`;
- `CommitmentPlanBinding`;
- `Fulfillment`;
- immutable `FulfillmentReview`;
- `FulfillmentDispute`;
- quantity handling through exact decimal strings;
- Planner binding/materialization for time-based obligations;
- exact same-Context Asset and Content Evidence Reference reuse;
- explicit accept / reject / request-clarification review;
- correction-as-new-Fulfillment;
- dispute and explicit resolution;
- Commitment/Fulfillment source events in the unified Context Timeline.

## Contract binding

Every Commitment binds to one exact `ContractVersion`.

If the Contract later activates an amendment, existing Commitments remain governed by the ContractVersion under which they were created. No historical Commitment is silently rebound to newer terms.

The responsible Actor and beneficiary/reviewer must be exact parties of that ContractVersion.

## Planner composition

A Commitment may create/bind one Plan.

Planner continues to own:

- intended dates/times;
- materialized Occurrences;
- actual start/end/completion;
- occurrence evidence.

The Plan/Occurrence records Commitment provenance, but completing a Planner Occurrence does **not** create Fulfillment automatically.

The responsible Actor explicitly submits Fulfillment and may choose a completed bound occurrence as actual-time/evidence input.

## Fulfillment authority

Fulfillment records actual claimed performance, including:

- exact Commitment;
- optional PlanOccurrence;
- performed quantity;
- actual start/end/duration when available;
- notes;
- exact same-Context Assets;
- exact same-Context Content Evidence References;
- immutable correction lineage.

A submitted Fulfillment is not accepted performance until an authorized beneficiary/reviewer records an explicit review.

## Review semantics

Review decisions are explicit:

- Accept;
- Reject;
- Request clarification.

Accept contributes the Fulfillment quantity to Commitment accepted progress.

Reject preserves the submitted Fulfillment and review history but contributes no accepted quantity.

Request clarification leaves the original immutable and requires a new correction/replacement Fulfillment rather than editing past facts.

## Correction semantics

A correction creates a new Fulfillment linked to the prior Fulfillment.

The original Fulfillment remains unchanged and becomes historical/corrected truth.

Review authority applies to the replacement Fulfillment separately.

## Dispute lifecycle

An accepted/rejected review outcome may be explicitly disputed by an authorized party.

While an accepted Fulfillment is disputed, its quantity is excluded from accepted Commitment progress.

Resolution explicitly restores an accepted outcome or resolves as rejected. The dispute record and original review remain durable history.

## Story proof — Riverside workday

Alice and Bob have an Active ContractVersion for Riverside work.

Alice creates **Riverside construction workday**:

- Bob = responsible Actor;
- Alice = beneficiary/reviewer;
- required quantity = 1 day;
- Commitment is bound to the exact active ContractVersion.

A Plan is created for the selected workday.

Bob:

1. starts the occurrence;
2. completes it;
3. attaches exact evidence if needed;
4. explicitly submits Fulfillment for quantity 1.

Alice explicitly reviews that Fulfillment.

Only after Alice accepts does accepted Commitment progress become 1.

No money is created by this review.

## Timeline composition

Commitment/Fulfillment lifecycle events are projected into the existing Contract Context Timeline.

Timeline stores no duplicate obligation/performance authority; source records remain authoritative.

## Authority boundary

Phase 16 creates no automatic:

- Financial Obligation;
- amount owed;
- invoice/receivable/payable;
- Accounting JournalEntry;
- Settlement;
- payment truth;
- ownership/equity;
- employment status.

An accepted Fulfillment means performance was accepted under the Commitment. **Phase 17** must explicitly derive any financial consequence.

## Remote validation

Final runtime CI `36093668972` proves:

- PHPUnit: **495 passed / 2989 assertions**;
- PHPStan: no errors;
- Pint: clean;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler/database-queue smoke: passed;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused tests prove:

- exact ContractVersion binding across later amendments;
- Planner/Occurrence Commitment provenance;
- completed occurrence → explicit Fulfillment submission;
- exact evidence reuse;
- explicit review;
- accepted quantity derivation;
- clarification → immutable correction;
- dispute removes accepted quantity until explicit resolution;
- cross-Context evidence rejection;
- outsider denial;
- Contract UI → Commitment handoff;
- Timeline composition;
- no automatic JournalEntry/financial authority.

## Deferred

- Financial Obligation derivation;
- earned/owed/outstanding/disputed financial values;
- explicit Accounting posting;
- Settlement/payment;
- multi-step approval policies beyond current beneficiary/reviewer;
- richer partial-delivery UX;
- automatic domain-specific Commitment templates;
- local/browser/mobile/RTL/accessibility cumulative acceptance.

## Exit gate

Phase 16 exits because the system now distinguishes agreement, obligation, planning, actual performance, review, correction and dispute as separate authoritative states without creating financial consequences implicitly.

Next: **Phase 17 — Financial Obligation + Settlement bridge**.
