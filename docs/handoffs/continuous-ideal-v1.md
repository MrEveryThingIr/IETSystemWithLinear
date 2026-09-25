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

Phase 17 is integrated on `integration/ideal-v1` at merge checkpoint `ec284490ac1e43b384abfbe9ee7bba1bf15e84f9`.

Phase 18 — Journey / Relationship / Domain Blueprints — is remote-green on `feat/ideal-v1-18-domain-blueprints`: kernel `f6f31eac3941e9efef188d07a6ffd9a091cbcb81` / CI `36118370719` (507 tests / 3109 assertions), runtime/UI `e4c9cb4cce39a1e0a37bad6ae72e97b6ab5feb77` / CI `36119387963` (510 tests / 3138 assertions), with documentation/manual closure pending its final feature-branch gate.

After Phase 18 integration, continue directly with **Phase 19 — Need / Offer Matching** from the integration trunk.

Persistent Phase 18 boundary: Domain/Journey Blueprints are immutable versioned composition recipes. They may configure terminology, recommended capabilities, useful Content templates and guided-entry defaults, but they never grant authorization or create Proposal, Contract, Commitment, Fulfillment, Financial Obligation, Accounting or Settlement authority. Relationship/Plan instances preserve the exact source BlueprintVersion and never silently upgrade.

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
