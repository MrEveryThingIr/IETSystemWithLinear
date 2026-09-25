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

Phase 14 is integrated on `integration/ideal-v1` at merge checkpoint `dfdfd33c99db57e36f11591fd9a84a1c32cbb333`.

Phase 15 — Contract, ContractVersion and explicit acceptance — is closure-green on `feat/ideal-v1-15-contract-version-acceptance` at `188e55f494d728b4e3cb7867385ce1320567f831` / CI `36056881847` (487 tests / 2907 assertions), pending integration.

After Phase 15 integration, continue directly with **Phase 16 — Commitment + Fulfillment** from the integration trunk.

Persistent Phase 15 boundary: ContractVersion acceptance is authoritative agreement truth for the exact party/role/terms/effective-time snapshot. It must not silently create Commitment, Planner Occurrence, Fulfillment, Financial Obligation, Accounting posting, Settlement/payment, ownership or employment truth. Phase 16 owns Commitment/Fulfillment.

Do not reintroduce AI runtime until the dedicated later AI milestone.

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
