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

The bounded Publishable Ideal-v1 code line is integrated on `integration/ideal-v1`.

The latest release hardening is the native-Persian UI localization pass:

- 11 previously absent Persian locale modules were added;
- `lang/fa/access.php` and `lang/fa/intents.php` no longer load English;
- user-facing wording across Access, Needs/Offers, Relationships, Proposals, Contracts, Commitments/Fulfillment, Planner, Finance/Accounting, Collaboration, Content, Reader/Studio, Notifications and shared UI vocabulary was rewritten toward simple native Persian rather than literal translation;
- repeated implementation jargon such as Actor/Context/Blueprint/Studio is no longer exposed untranslated in Persian UI copy where a clear Persian term exists;
- `LocalizationParityTest` requires every real English translation leaf to exist in Persian and forbids Persian locale files from loading English passthroughs;
- Laravel's locale-specific validation aliases remain intentionally allowed in addition to the English validation-key baseline.

Release evidence:

- localization feature head: `1a6fc7831b11588ef494fa8b36b3c2e465223bd5`;
- feature CI: `36160123238` — green;
- PR #25 CI: `36160463461` — green;
- integration merge: `edff668b6e8ad6c4a58a7d6c08bb54a821ce9662`;
- integration CI: `36160798652` — green;
- full regression: **545 tests / 3376 assertions**;
- MySQL 8.4 + SQLite migrations, Pint, PHPStan, Vite, npm audit and Composer audit: green;
- `release/ideal-v1-rc-4` is the immutable pre-doc-sync localization checkpoint;
- `release/ideal-v1-rc-5` is the docs-synchronized release candidate to use for owner-local/browser acceptance.

Do **not** mutate old RC refs. Any browser defect must become an automated regression, a correction branch from the current integration line, a complete CI pass, an integration merge, and a newly numbered RC.

Do **not** implement AI Copilot, Generic Workflow, Reputation or Recommendations as part of this release. They remain post-v1 unless a concrete browser/release defect requires otherwise.

Persian UI localization is hardened, but the seeded/System Manual content is still English-canonical. A full native-reviewed Persian Manual is a separate content-translation milestone, not a hidden requirement for this RC.

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
