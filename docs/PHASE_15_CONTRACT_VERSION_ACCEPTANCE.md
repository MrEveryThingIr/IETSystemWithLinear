# Phase 15 — Contract, ContractVersion and explicit acceptance

## Status

Remote runtime implementation is complete and green on `feat/ideal-v1-15-contract-version-acceptance`.

Kernel checkpoint:

~~~text
SHA: cd39e8861844b598e3e9fe81f659b93648e7c4b5
GitHub Actions: 36054184003
483 tests / 2869 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Final runtime/UI checkpoint:

~~~text
SHA: df63697b3734bc3a8dfe1b70f58655d4b2c9da72
GitHub Actions: 36055829092
487 tests / 2903 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Documentation closure checkpoint:

~~~text
SHA: 188e55f494d728b4e3cb7867385ce1320567f831
GitHub Actions: 36056881847
487 tests / 2907 assertions
System Manual: 20 chapters
~~~

Baseline: Phase 14 integration merge `dfdfd33c99db57e36f11591fd9a84a1c32cbb333`.

## Purpose

Create exact party-specific authoritative terms whose version, parties, roles, acceptance evidence and effective time remain durable and explainable.

## Implemented kernel

Phase 15 adds:

- `Contract`;
- dedicated Contract `Context`;
- immutable `ContractVersion`;
- immutable per-version `ContractVersionParty` snapshots;
- immutable `ContractAcceptance`;
- immutable `ContractEvent`;
- sealed `contract-terms` Content for direct Contracts/amendments;
- explicit Contract creation from an accepted ProposalVersion;
- direct Contract creation with optional active Relationship provenance;
- required-party exact-version acceptance;
- activation/effective-time;
- future-effective amendments;
- automatic supersession when an accepted amendment reaches its effective time.

## Proposal → Contract authority boundary

Accepted Proposal state remains negotiation truth only.

Creating a Contract from an accepted Proposal is a new explicit Action.

The initial ContractVersion:

- records the accepted ProposalVersion as provenance;
- references the **same exact sealed terms Content revision**;
- snapshots Proposal parties and roles into ContractVersionParty records;
- creates fresh Contract acceptance state.

ProposalDecision rows are never reused as ContractAcceptance rows.

Every required Contract party must explicitly accept the exact ContractVersion again.

One accepted ProposalVersion may create at most one Contract authority chain.

## Direct Contract path

Contract creation does not depend on Need/Offer/Matching or Proposal.

Known parties may create a direct Contract, optionally from an active Relationship.

The direct path creates:

- Contract identity;
- dedicated Contract Context;
- sealed Contract terms Content;
- ContractVersion 1;
- exact party/role snapshot;
- proposer acceptance;
- pending acceptance for other required parties.

Relationship participation is provenance/context only; it never counts as Contract acceptance.

## Exact-version acceptance

Contract acceptance is bound to one exact ContractVersionParty.

The system records:

- exact version;
- exact Actor/role snapshot;
- accepting User;
- timestamp.

Conversation text such as “I accept” creates no ContractAcceptance.

Once all required parties accept the same ContractVersion, the version becomes Accepted.

If its effective time has arrived, it becomes Active immediately. Otherwise it remains Accepted until the effective time.

## Effective-time and amendments

An active ContractVersion cannot be edited.

A party may propose a future amendment only when:

- the Contract is active;
- there is no other pending amendment;
- the proposer is a party to the active version;
- the new effective time is in the future.

Amendment terms are published as a new sealed Content revision inside the Contract Context.

The amendment copies the active version's party/role snapshot into a new ContractVersion and requires fresh acceptance from every required party.

Even after all required parties accept the amendment, the old ContractVersion remains Active until the new effective time.

At that instant:

1. the old version becomes Superseded;
2. its `effective_until` is recorded;
3. the new version becomes Active;
4. immutable lifecycle events preserve the transition.

The scheduler runs `contracts:activate-due` every minute to activate due accepted versions.

## Product experience

The advanced product now exposes **Contracts**.

Users can:

- create a direct Contract;
- create a direct Contract from an active Relationship;
- create a Contract explicitly from an accepted Proposal;
- enter readable terms and exact roles;
- choose the effective time in their timezone;
- inspect sealed terms/manifest identity;
- see party-by-party acceptance state;
- explicitly accept the pending ContractVersion;
- inspect immutable version history;
- propose future-effective amended terms;
- use Contract Conversation / Content / Timeline workspace;
- navigate back to source Proposal or Relationship.

## Story proof — direct Alice/Bob paid work

Alice creates **Workshop paid work**.

- Alice role: employer.
- Bob role: worker.
- Version 1 contains the exact paid-work terms.
- Alice's creation action records Alice's acceptance.
- Bob can discuss in the Contract Context, but typing “I accept” does not accept the ContractVersion.
- Bob explicitly chooses **Accept ContractVersion**.
- With all required parties accepted and the effective time due, version 1 becomes Active.

No Planner Commitment, Fulfillment, financial obligation, JournalEntry or payment is created by Contract activation.

## Story proof — Riverside Proposal → Contract

Alice/Bob/Carol first reach an Accepted Riverside ProposalVersion.

Alice explicitly chooses **Create Contract from Proposal**.

The Contract:

- records the ProposalVersion provenance;
- reuses its exact sealed terms revision;
- snapshots project-owner / builder / site-coordinator roles;
- starts with fresh Contract acceptance evidence.

Proposal acceptance does not carry into Contract acceptance.

Only after every required Contract party explicitly accepts the ContractVersion can it activate.

## Timeline composition

ContractEvent records project into the existing unified Context Timeline.

Timeline stores no duplicate Contract transaction and links back to the source Contract.

Contract/ContractVersion/ContractAcceptance remain authority.

## Authorization boundary

Only active verified user-backed Actors may currently become Contract parties.

A Contract is visible to its creator and version parties.

Contract Context interaction is allowed only through ContractPolicy participation.

Generic Content review/manage permissions are not granted merely because a user can participate in a Contract Context.

An unrelated Actor cannot open the Contract or its Context.

## Non-implications

An Active Contract is authoritative agreement truth.

It does **not** itself prove:

- a workday was scheduled;
- a Commitment was created;
- work actually happened;
- Fulfillment was submitted or accepted;
- money was earned/owed;
- a Financial Obligation exists;
- Accounting was posted;
- Settlement/payment occurred;
- ownership/equity/employment state exists outside the explicit terms and later domain actions.

Phase 16 owns Commitment/Fulfillment authority.

## Remote validation

Final runtime CI `36055829092` proves:

- PHPUnit: **487 passed / 2903 assertions**;
- PHPStan: clean;
- Pint: clean;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler/database-queue smoke: passed;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused tests prove:

- direct paid-work Contract activation;
- accepted Proposal → fresh Contract acceptance;
- exact sealed terms provenance;
- exact party/role snapshots;
- Conversation wording has no acceptance authority;
- duplicate ProposalVersion → Contract authority is rejected;
- immutable ContractVersion / ContractAcceptance history;
- outsider isolation;
- future amendment acceptance without premature supersession;
- due activation supersedes the prior version at the intended instant;
- UI Contract/Proposal/Relationship handoffs;
- Contract Timeline projection;
- no silent Planner/Accounting side effects.

## Deferred

- Commitment generation/binding;
- Planner materialization from Contract Commitments;
- Fulfillment/evidence review;
- dispute/correction lifecycle around Fulfillment;
- Financial Obligation/Settlement;
- richer optional-party/role-change amendment UX;
- notifications;
- organization/system Actor contracting;
- local/browser/mobile/RTL/accessibility cumulative acceptance.

## Exit gate

Phase 15 exits because Contract authority is now explicit, exact-version, immutable after acceptance, separately accepted from Proposal negotiation, time-aware, amendable only through future versions, and cleanly separated from downstream work/finance authority.

Next: **Phase 16 — Commitment + Fulfillment**.
