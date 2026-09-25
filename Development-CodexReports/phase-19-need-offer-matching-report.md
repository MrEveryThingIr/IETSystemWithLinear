# Phase 19 Closure — Need / Offer Matching

## Result

Phase 19 adds explainable, privacy-safe Need/Offer discovery without introducing a Match authority domain.

Runtime proof:

- branch: `feat/ideal-v1-19-need-offer-matching`
- baseline: `1784ec762e7f424d9c3b6e614664af96ea918a11` (Phase 18 integration)
- runtime SHA: `532901b4e11d78cb5d848ca4bb6039632c62f3a6`
- CI: `36124849892`
- **517 tests / 3192 assertions**
- Pint 645 files green
- PHPStan/Vite/migration/ops/backup/security gates green

## Architectural result

Matching is a derived read model over `ActorProfileIntent`.

There is intentionally no `matches` table and no lifecycle state that could be mistaken for agreement or obligation.

The hard minimum is opposite direction + same canonical Concept + compatible subject/arrangement. Explicit quantity/place/time/value constraints narrow candidates when both sides provide them.

## Privacy result

The normal Intent policy is applied before a candidate can appear.

Visible Intent does not imply visible Profile identity. The Match/Relationship UI preserves that distinction and no longer leaks a private Profile username through a visible Intent handoff.

## Handoff result

Match → Proposed Relationship → explicit acceptance → optional Proposal.

The Relationship stores both source Intent IDs as immutable provenance and transactionally revalidates the selected candidate.

Proposal still requires an Active Relationship.

## Negative guarantees

Matching itself creates no Proposal, Contract, Commitment, Fulfillment, Financial Obligation, Settlement, JournalEntry, ownership or employment truth.

The explanation count is not a universal score.

Concept hierarchy is not silently treated as substitutable semantics.

## Documentation result

Closure adds:

- `docs/PHASE_19_NEED_OFFER_MATCHING.md`;
- System Manual Chapter 24;
- matching contextual Help mapping;
- Checkpoint 19 in the local acceptance worksheet;
- synchronized current state / roadmap / continuous handoff.

## Local acceptance

Deferred under the continuous remote execution contract. The cumulative owner/browser gate is recorded in `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

## Next

Phase 20 — Groups / Communities social composition.
