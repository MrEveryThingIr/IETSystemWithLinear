# Phase 19 — Need / Offer Matching

## Status

Runtime implementation is green on `feat/ideal-v1-19-need-offer-matching`.

Runtime checkpoint:

- SHA: `532901b4e11d78cb5d848ca4bb6039632c62f3a6`
- CI: `36124849892`
- Result: **517 tests / 3192 assertions**
- Pint: 645 changed PHP files green
- PHPStan: green
- Vite: green
- migration rollback/reapply: green
- scheduler/database queue smoke: green
- SQLite backup/restore smoke: green
- npm audit: 0 vulnerabilities
- Composer audit: no advisories

Final documentation/manual closure and integration PR remain the last remote gates.

## Purpose

Add explainable discovery over the already-proven Intent → Relationship → Proposal → Contract path without inventing a new authority source.

A Match is a derived compatibility view. It is not persisted business truth.

## Matching source

The source is one existing Active `ActorProfileIntent` owned by the current user.

Candidate Intents must:

- be Active;
- have the opposite Need/Offer direction;
- belong to a different Actor;
- already be visible under `ActorProfileIntentPolicy`;
- satisfy the deterministic compatibility rules.

The Match page is owner-only.

## Compatibility rules

Phase 19 deliberately starts conservatively.

### Required

- opposite Need/Offer direction;
- same canonical Concept identity;
- compatible subject kind;
- compatible arrangement kind.

`other` remains an open/unspecified subject or arrangement rather than a contradiction.

### Applied when both sides specify enough data

- quantity/unit;
- location;
- origin/destination;
- date-range overlap;
- same-timezone clock-window overlap;
- same-timezone weekly/monthly recurrence overlap;
- currency;
- cash basis;
- cash-range overlap;
- exchange preference as an explanation dimension.

Large decimal quantity/cash comparisons avoid floating-point conversion.

Cross-timezone HH:MM strings are not treated as directly comparable.

## Concept boundary

Canonicalized merged Concepts may match.

Phase 19 does **not** assume Concept hierarchy edges mean substitutability. Descendant/broader matching remains future work until an explicit scheme/rule says that substitution is valid.

## Explainability

`IntentMatchResult` returns:

- the candidate Intent;
- stable explanation keys;
- the number of aligned dimensions.

The aligned-dimension count is descriptive ordering only; it is not authoritative quality, fitness, trust or entitlement.

## Privacy

Matching never expands Intent visibility.

An explicit visible Intent can be discovered even when its owning Profile remains private, but the Match and Relationship handoff do not reveal the private Profile username merely from Intent visibility.

Selected candidates are revalidated directly at handoff time so a stale/tampered URL cannot bypass current status, visibility or compatibility.

## Relationship provenance

No Match row/table is created.

When the owner explicitly starts a Relationship from a candidate:

- `relationships.originating_intent_id` preserves the owner's source Intent;
- `relationships.matched_intent_id` preserves the selected counterpart Intent;
- Relationship events preserve both IDs;
- both Intent owners must be Relationship participants;
- the pair must still be compatible inside the transactional Relationship Action.

These provenance fields become immutable with Relationship identity.

## Authority boundary

Discovery creates no:

- Relationship until the user explicitly submits;
- active Relationship until the invited participant explicitly accepts;
- Proposal;
- Contract;
- Commitment;
- Fulfillment;
- Financial Obligation;
- Settlement;
- JournalEntry;
- employment/ownership authority.

A sourced Proposal still requires an **Active Relationship**. Phase 19 does not bypass consent by linking directly from Match to Proposal.

## UX

From the Intent Directory, the owner of an Intent receives **Find matches**.

The Match page shows:

- the source Intent;
- compatible opposite-direction candidates;
- policy-safe participant identity;
- human-readable compatibility reasons;
- **Start relationship**.

No opaque percentage/ranking score is shown.

## Proofs

Automated tests prove:

1. Alice's construction Service Need finds Bob's compatible Service Offer.
2. Alice's Capital Need finds Carol's compatible Capital Offer.
3. wrong location/value constraints are excluded.
4. private Intents are excluded.
5. merged canonical Concept identity works.
6. unrelated Concepts do not match.
7. discovery creates no Relationship/Proposal/Contract/financial/accounting truth.
8. private Profile identity remains hidden.
9. matched Relationship creation preserves both Intent origins.
10. a tampered incompatible candidate is rejected before Relationship creation.

## Migration

`database/migrations/2026_09_25_030000_add_matching_provenance_to_relationships.php`

The migration adds only exact counterpart Intent provenance. It does not create a Match table.

## Next

Phase 20 — **Groups / Communities social composition**.

Groups should compose existing independent kernels into focused social/application environments. Do not duplicate module storage inside Groups.
