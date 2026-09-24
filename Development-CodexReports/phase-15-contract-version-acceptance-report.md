# Phase 15 Closure — ContractVersion and Explicit Acceptance

## Result

Phase 15 runtime is remotely complete.

- Branch: `feat/ideal-v1-15-contract-version-acceptance`
- Baseline: `dfdfd33c99db57e36f11591fd9a84a1c32cbb333`
- Kernel checkpoint: `cd39e8861844b598e3e9fe81f659b93648e7c4b5` / CI `36054184003` — 483 tests / 2869 assertions
- Final runtime/UI checkpoint: `df63697b3734bc3a8dfe1b70f58655d4b2c9da72` / CI `36055829092` — 487 tests / 2903 assertions
- Documentation closure checkpoint: pending closure CI
- Local/browser/mobile/RTL/accessibility acceptance: deferred cumulatively

## Authority result

Contract is now a distinct authoritative domain, not an alias for Proposal or Conversation.

Proposal acceptance is never reused as Contract acceptance.

Each required Contract party explicitly accepts the exact ContractVersion.

## Version result

Accepted/effective Contract terms are immutable.

Amendment creates a new sealed ContractVersion with fresh acceptance.

A future-effective accepted amendment does not rewrite or prematurely supersede today's active version.

When its effective time arrives, the previous version is superseded with an exact effective-until boundary and the new version activates.

## Provenance result

Contracts can be created directly, from an active Relationship, or explicitly from an accepted ProposalVersion.

Proposal-sourced ContractVersion 1 references the same exact sealed terms revision and snapshots Proposal parties/roles.

One source ProposalVersion cannot create duplicate Contract authority.

## Composition result

Contract owns a dedicated Context and composes Conversation, Content and Timeline without making those surfaces Contract authority.

Contract Events project into Timeline with source links.

## Negative result

Contract activation does not create Planner Commitments, Fulfillment, Financial Obligation, Accounting posting, Settlement/payment, ownership, or employment records.

## Validation

Final runtime CI `36055829092` is green:

- 487 tests / 2903 assertions;
- PHPStan clean;
- Pint clean;
- Vite green;
- migration rollback/reapply green;
- scheduler/database-queue/backup smoke green;
- npm audit 0 vulnerabilities;
- Composer audit no advisories.

## Next

Phase 16 — Commitment + Fulfillment.
