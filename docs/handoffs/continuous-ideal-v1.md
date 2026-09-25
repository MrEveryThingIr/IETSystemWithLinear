# Continuous Ideal-v1 Handoff

## Mission

Implement the full accepted Ideal-v1 roadmap continuously on GitHub with remote automated gates at every milestone and deferred owner-local/browser acceptance.

## Read first

1. `AGENTS.md`
2. `.ai/rules/index.md` + matching rules
3. `docs/PROJECT_COMPASS.md`
4. `docs/CURRENT_STATE.md`
5. `docs/TARGET_ARCHITECTURE.md`
6. `docs/PRODUCTION_ROADMAP.md`
7. `docs/CONTINUOUS_REMOTE_EXECUTION.md`
8. `docs/EXAMPLE_STORY_WORLD.md`
9. active milestone contract/report

## Git line

Integration trunk:

~~~text
integration/ideal-v1
~~~

Root baseline:

~~~text
2c7a5c35a31fe86d761a1cafd189560bec220784
~~~

First reconstructed office-foundation commit:

~~~text
79367e717a74b877fbbb81c4ea9c9ab79fcbf773
~~~

This reconstruction intentionally removes the unused AI assistance / Development Origin runtime while retaining the new Access Invitation + Intent Registry product work.

## Current objective

Phase 22 is integrated on `integration/ideal-v1` at `25a24e0fd0853347d73ec7b262ac274f76f40c6a`.

The active branch is `feat/ideal-v1-publishable-hardening`. Its purpose is to close inherited cross-roadmap defects and produce the first bounded publishable Ideal-v1 release candidate, not to continue speculative roadmap expansion.

Closed/active hardening scope:

- reserved-email Access Invitations are single-use while unreserved links retain bounded multi-use;
- Intent Directory authorization is query-level before real pagination;
- Directory filter/query/highlight handoff is coherent;
- Profile Intent management is reconciled with guided subject/arrangement/cash/exchange facets;
- publication boundary and first-month learning posture are recorded in `docs/PUBLISHABLE_V1_RELEASE_GATE.md`.

Do **not** implement AI Copilot for this release. Preserve Phase 26 as an optional future draft/preparation layer over existing validated Actions. Generic Workflow, Reputation and Recommendations are likewise post-v1 unless a concrete release-blocking defect proves otherwise.

Pre-integration release-gate candidate `38f95175040234593bc927f895954c893a38e9fd` is green on CI run `36143475001` with 543 tests / 3316 assertions and all quality/ops/security gates passing. The remaining remote sequence is canonical-doc exact-head CI → PR into `integration/ideal-v1` → green integration CI → freeze one release-candidate ref/SHA. Browser acceptance remains the owner's final gate and must not be claimed remotely.

## Persistent product rules

- Content is one independent versioned system; GroupSpace is only one Context kind.
- Content has a home/origin Context for authoring/authorization but may be presented/referenced elsewhere.
- normal presentation may follow the current published revision; evidence must pin an exact published revision/target.
- Contexts compose modules; they do not require duplicate module tables.
- user journeys progressively activate capabilities rather than selecting a giant universal type.
- Need/Offer is intent, not Match/Contract/obligation.
- Relationship is an explicit direct coordination boundary, not Group Membership, Contract, ownership, employment, financing rights or payment.
- Relationship Context is writable only after explicit participant consent and becomes read-only when terminal.
- Conversation is collaboration evidence, not authoritative acceptance.
- finance uses explicit domain-event → obligation → accounting actions; balances are derived.
- user-facing UX uses plain actions while specialized kernels retain authority.
- Alice/Bob/Carol/Diego examples are canonical across docs/tests.

## Interruption recovery

If interrupted:

1. inspect latest `integration/ideal-v1` SHA and CI;
2. inspect active feature branch/report if one exists;
3. never restart from chat memory;
4. update this handoff after each remotely integrated milestone.
