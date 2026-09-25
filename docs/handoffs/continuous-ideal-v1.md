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

Phase 21 is integrated on `integration/ideal-v1` at `0b605a19643f2b37dce7c88332e1baf284a93a33`.

Phase 22 — Realtime + Notifications — is runtime-green on `feat/ideal-v1-22-realtime-notifications`:

- runtime SHA `cea1cb4d6775b855e94146dfc75029ec1264ac07`;
- CI `36134998196`;
- **537 tests / 3297 assertions**;
- Pint 686 PHP files;
- PHPStan/Vite/migration rollback-reapply/scheduler/database-queue/backup/security gates green;
- durable notification outbox/database inbox + private Reverb/Echo transport implemented.

Closure docs/report are being synchronized before integration.

A historical Codex office-alpha audit was revalidated against the Phase 22 head. Do **not** begin Phase 23 immediately after integration without first closing the still-valid cross-roadmap correctness debt:

- Intent Directory authorization currently occurs after a hard 200-row limit and lacks real pagination;
- reserved-email Access Invitations still allow impossible multi-use semantics;
- post-create Intent highlight is emitted but not consumed;
- Profile Intent management has not caught up with subject/arrangement/cash/exchange facets;
- stale canonical documentation must be reconciled.

After Phase 22 integration, create one focused Ideal-v1 hardening checkpoint from the integration trunk, close/prove those inherited findings, and only then continue with **Phase 23 — Generic Workflow extraction**.

Do not reintroduce AI runtime until the dedicated Phase 26 milestone.

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
