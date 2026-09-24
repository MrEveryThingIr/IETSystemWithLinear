# Phase 14 — Proposal + Negotiation

## Status

Remote runtime implementation is complete and green on `feat/ideal-v1-14-proposal-negotiation`.

Kernel checkpoint:

~~~text
SHA: 3a30f21af06e478fc269d7db1e4085ce73500133
GitHub Actions: 36047890895
474 tests / 2771 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Final runtime/UI checkpoint:

~~~text
SHA: a27a2538161ff36d123eef1bd0f9d9c153298987
GitHub Actions: 36048778108
477 tests / 2799 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Baseline: Phase 13 integration merge `e3bf30dfd344939bd6d3ca939f5149133bff6f7a`.

## Purpose

Turn a direct request or discovered opportunity into explicit proposed terms that parties can discuss, revise, accept, reject or request changes against before any Contract exists.

## Implemented kernel

Phase 14 adds Proposal, ProposalParty, a dedicated Negotiation Context, immutable ProposalVersion, immutable ProposalDecision, ProposalEvent history, and exact sealed Content revisions for every proposed terms version.

The new Context kind is `negotiation`.

## Exact-version authority

Each ProposalVersion points to one exact published/sealed Content revision.

A party decision is bound to one ProposalVersion and one ProposalParty. A new ProposalVersion never inherits decisions from an older version.

The Actor who proposes a new version records Accepted for that exact version as part of the authoritative Action. Every other required party must respond again.

A Proposal becomes `accepted` only when every required party has Accepted the same current version.

## Request-change semantics

Request changes records an immutable decision/event against the current version. It never mutates that version.

Any current Proposal party may publish the next sealed terms version. Prior versions and prior decisions remain durable negotiation history.

## Relationship composition

A Proposal may be created directly or from an active Relationship.

When sourced from a Relationship, the source Relationship is preserved, only active Relationship participants may become Proposal parties, and the Proposal receives its own Negotiation Context.

Relationship authority and Proposal authority remain separate.

## Conversation, Content and Timeline

Negotiation Context composes Conversation, Content and Timeline.

Conversation is discussion evidence only. Typing “I accept” has no Proposal authority; only the explicit Accept Action records exact-version acceptance.

Proposal events appear in the unified Timeline with source links.

Accepted, Rejected and Cancelled Proposals make the Negotiation Context read-only for further interaction.

## Story proof — Riverside

Alice creates **Riverside construction collaboration** with Bob and Carol.

Version 1: Alice proposes/accepts; Bob accepts; Carol requests clarification.

Carol publishes version 2 with clarified site responsibilities. Carol is Accepted as proposer, while Alice and Bob must decide again. Alice accepts, then Bob accepts. Only then does the Proposal become Accepted.

Version-1 decisions remain visible and never count toward version 2.

## Authority boundary

An accepted Proposal is still **not a Contract**.

Phase 14 creates no Contract/ContractVersion, Commitment, Fulfillment authority, employment, ownership/equity, Financial Obligation, Accounting posting, Settlement or payment truth.

Phase 15 must explicitly create Contract authority.

## Remote validation

Final runtime CI `36048778108` proves:

- PHPUnit: **477 passed / 2799 assertions**;
- PHPStan: clean;
- Pint: clean;
- Vite build: green;
- migration rollback/reapply: green;
- scheduler/database-queue/backup smoke: green;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused tests prove multi-party exact-version acceptance, fresh decisions per version, request-change/revision history, terminal rejection, outsider isolation, immutable version/decision history, sealed terms Content, terminal read-only Conversation, Timeline projection, and Relationship handoff without authority collapse.

## Deferred

Contract creation/acceptance, Commitment/Fulfillment, Financial Obligation/Settlement, matching-driven proposals, notification delivery, richer optional-party UX, and cumulative local/browser/mobile/RTL/accessibility acceptance remain later work.

## Exit gate

Phase 14 exits because parties can negotiate exact immutable versions with explicit version-scoped decisions while accepted Proposal state remains separate from Contract authority.

Next: **Phase 15 — Contract, ContractVersion and explicit acceptance**.
