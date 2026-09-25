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

Phase 16 is integrated on `integration/ideal-v1` at merge checkpoint `d4289f46d4726d5fc5a581abb43ce6a1efc6dfc8`.

Phase 17 — Financial Obligation + Settlement bridge — is runtime-green on `feat/ideal-v1-17-financial-obligation-settlement` at `47bae48638345a807623ceb7f1ae44866d09d9e6` / CI `36115933156` (502 tests / 3065 assertions), pending documentation closure/integration.

After Phase 17 integration, continue directly with **Phase 18 — Journey / Relationship / Domain Blueprints** from the integration trunk.

Persistent Phase 17 boundary: accepted Fulfillment is performance truth, Financial Obligation is explicit shared economic-source truth, Personal Accounting posting is actor-owned ledger truth, Settlement is counterparty-confirmed payment-record truth, and Settlement Accounting is another explicit per-Actor posting. Never mutate a magic balance or infer payment/obligation from Conversation, Content, Planner completion or Contract activation.

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
