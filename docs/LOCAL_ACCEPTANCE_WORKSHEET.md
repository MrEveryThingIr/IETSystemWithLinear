# IET Local Acceptance Worksheet

## Purpose

This worksheet is intentionally cumulative. Remote development may continue without local browser interruption, but every checkpoint must remain reproducible later.

**Never run `migrate:fresh` against the owner's continuing database. Back up first.**

## Common preparation

For every checkpoint:

~~~bash
git status --short
git fetch origin
~~~

If local tracked changes exist, preserve them before switching. Do not reset/discard them casually.

Environment for the focused first release experience:

~~~text
APP_NAME=IET
IET_RELEASE_PROFILE=office_alpha
~~~

After environment changes:

~~~bash
php artisan optimize:clear
~~~

---

## Checkpoint F0 — Clean pre-AI office foundation

Remote branch:

~~~text
integration/ideal-v1
~~~

Remote runtime checkpoint:

~~~text
SHA: f5b55fb3f1d426e995549efc90856cfd9ac60b34
CI: 35924838454
Result: 411 tests / 2257 assertions; Pint 366 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

### Local sync

~~~bash
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD
# the F0 runtime checkpoint is:
# f5b55fb3f1d426e995549efc90856cfd9ac60b34

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/AccessInvitationJourneyTest.php \
  tests/Feature/IntentDirectoryReleaseTest.php \
  tests/Feature/ReleaseExperienceTest.php \
  tests/Feature/InvitationJourneyTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story

Use `docs/EXAMPLE_STORY_WORLD.md`.

- [ ] Diego/admin can create a standalone Access Invitation.
- [ ] Alice can inspect the welcome page before registration.
- [ ] Alice registers without joining a Group.
- [ ] verification returns Alice to Get Started.
- [ ] Alice records Property / Construction Service / Capital Needs or Offers.
- [ ] cash/mixed-value preference is visibly non-binding.
- [ ] Bob, as a second verified user, sees only intents allowed by visibility.
- [ ] private Profile identity does not leak merely because an intent is shared.
- [ ] Diego can invite already registered Bob to a Group.
- [ ] unknown email is rejected by the Group-invitation creation flow.
- [ ] mobile + RTL smoke.

---

## Future checkpoint template

Each remote milestone appends a concrete section in this format:

~~~text
## Checkpoint <id> — <name>

Integration SHA:
CI run:
Migrations:
Remote report:

### Local sync
<exact commands>

### Focused tests
<exact commands>

### Browser story
[ ] exact user-visible action
[ ] expected durable result
[ ] visibility/authorization expectation
[ ] negative guarantee

### Continuity
What previous Alice/Bob/Carol/Diego objects should still exist and be reused.
~~~

The final release section will include the entire 0→100 path in one chronological browser script.


---

## Checkpoint 08 — Progressive Intent Journey v2

Remote branch:

~~~text
feat/ideal-v1-08-intent-journey
~~~

Remote feature checkpoint:

~~~text
SHA: eb82f8af1bac3c75e7bd5550db6d2a4dc6badfce
CI: 35926251822
Result: 428 tests / 2378 assertions; Pint 368 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

The final integration merge SHA is recorded in the Phase 8 closure report after PR merge.

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-08-intent-journey
git pull --ff-only origin feat/ideal-v1-08-intent-journey
git status --short
git rev-parse HEAD

php artisan optimize:clear

php artisan test --compact \
  tests/Feature/IntentJourneyV2Test.php \
  tests/Feature/IntentDirectoryReleaseTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
~~~

No Phase 8 migration is expected.

### Browser story

- [ ] Alice opens Record need / offer and first sees “What do you want to do?” rather than raw Need/Offer schema.
- [ ] Sell → step 2 can refine Thing/good to Property for Riverside Lot.
- [ ] Buy maps to Need + ownership transfer.
- [ ] Rent maps to Need + temporary use; Rent out maps to Offer + temporary use.
- [ ] Need service and Hire show Service/skill rather than unrelated Property/Capital choices.
- [ ] Offer service and Find work create Offer + Service semantics.
- [ ] Seek/Offer capital use Financing semantics and explicitly create no loan/equity.
- [ ] Seek/Offer collaboration create current collaboration Intent only.
- [ ] Something else still permits manual Need/Offer + subject + arrangement.
- [ ] Review shows the friendly journey and underlying interpretation.
- [ ] saving creates one ActorProfileIntent and no Submission/Evaluation/Match/Contract side effect.
- [ ] existing Directory/visibility/privacy behavior still passes.

---

## Checkpoint 09 — Published Content Library and reference/placement semantics

Remote branch:

~~~text
feat/ideal-v1-09-content-library-placement
~~~

Remote feature checkpoint:

~~~text
SHA: fdb7c1cbfbe1a81c284c67df79a71b0e5ee3074a
CI: 36020336046
Result: 432 tests / 2413 assertions; Pint 378 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_24_000000_create_content_placements_table.php
~~~

The final integration merge SHA is recorded by Git history/PR after the Phase 9 closure is merged.

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-09-content-library-placement
git pull --ff-only origin feat/ideal-v1-09-content-library-placement
git status --short
git rev-parse HEAD

php artisan optimize:clear

php artisan test --compact   tests/Feature/PublishedContentLibraryTest.php   tests/Feature/ModelFactoryTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
~~~

### Browser story

- [ ] Alice creates an Article/Report/Album in an authorized home Context and publishes it.
- [ ] Draft/unsealed Content does not appear in Content Library.
- [ ] The published item appears only for a viewer who is authorized to read it.
- [ ] Search, Content type/Blueprint and Concept filters narrow the Library meaningfully.
- [ ] An actor who can read the source/home Context and manage a second Context chooses **Present elsewhere**, selects the target Context and confirms **Present Content**.
- [ ] Bob, authorized only through the target Context, can open the placed published artifact.
- [ ] Bob cannot thereby open the source/home Context, Studio, revision-management or authoring surfaces.
- [ ] Bob cannot transitively present the artifact elsewhere unless he separately gains the required source-read and target-management authority.
- [ ] Publish a newer edition of the source Content; the normal placement follows the current published edition.
- [ ] Create/use an exact Evidence Reference to the earlier revision/block; confirm it still resolves that historical target after the newer publication.
- [ ] Remove the placement; Bob's target-only read access disappears.
- [ ] Present the same source/target again; the existing placement identity is reactivated rather than duplicated.
- [ ] Action visibility remains permission-aware and no placement silently changes ownership/home Context.
- [ ] Mobile, accessibility and RTL presentation remain part of the later cumulative polish/acceptance pass.


---

## Checkpoint 10 — Relationship + Relationship Context

Remote branch:

~~~text
feat/ideal-v1-10-relationship-context
~~~

Remote runtime checkpoint:

~~~text
SHA: 6cb21465489c14efffc589c41070f46160e029c2
CI: 36025406749
Result: 439 tests / 2481 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_24_010000_create_relationship_kernel.php
~~~

The final integration merge SHA is recorded by Git history/PR after the Phase 10 closure is merged.

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-10-relationship-context
git pull --ff-only origin feat/ideal-v1-10-relationship-context
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/RelationshipContextKernelTest.php \
  tests/Feature/RelationshipExperienceTest.php \
  tests/Feature/ModelFactoryTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Alice ↔ Bob

- [ ] Set a non-office-alpha release profile for the cumulative Ideal-v1 browser pass so Relationships navigation is visible.
- [ ] Alice has a visible Riverside construction/electrical Need.
- [ ] Bob opens **Needs, offers & services**, finds Alice's Intent, and chooses **Start relationship**.
- [ ] Purpose is inherited from the Intent and Alice is fixed as the counterpart; Bob enters roles such as service provider / client.
- [ ] Bob sends the request; one proposed Relationship and one Relationship Context exist.
- [ ] Before Alice accepts, Bob and Alice can inspect the request/Context but cannot create Content in it.
- [ ] An unrelated Carol/outsider session cannot open Bob/Alice's Relationship or Context.
- [ ] Alice opens **Relationships**, opens the pending request, and chooses **Accept relationship**.
- [ ] Relationship becomes active; active participants can now create/interact with ordinary Content in the Relationship workspace.
- [ ] No Group Membership was created for Alice/Bob by Relationship activation.
- [ ] No Match, Proposal, Contract, employment, ownership, loan/equity, Commitment, payment obligation or accounting entry was created.
- [ ] Alice or the managing participant ends the Relationship; the Context remains readable to participants and becomes read-only.

### Browser story — Alice ↔ Carol direct request

- [ ] Alice opens **Relationships → Start relationship** without an originating Intent.
- [ ] Alice selects the relevant Capital/Collaboration Concept.
- [ ] Alice enters Carol's exact username and explicit roles such as project owner / capital collaborator.
- [ ] Carol must explicitly accept before the Context becomes writable.
- [ ] Mentioning investment/property/ownership in labels or Content grants no ownership or financial rights.

### Continuity

Reuse the existing Alice, Bob, Carol, Riverside Lot/Home and Maple Housing Office examples. Relationship is now current implemented behavior. Conversation/Timeline is the next milestone; Proposal/Contract/Planner/Fulfillment/Accounting remain future until their own explicit phases.


---

## Checkpoint 11 — Conversation + Unified Timeline

Remote branch:

~~~text
feat/ideal-v1-11-conversation-timeline
~~~

Remote runtime checkpoint:

~~~text
SHA: 6dcd43a056290730eaab608b6291a96ce8ff4b62
CI: 36030315938
Result: 448 tests / 2533 assertions; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Kernel checkpoint:

~~~text
SHA: 0a0d0c4b38ee9629413a1db12536e9ffbfbb9b0c
CI: 36029544109
Result: 444 tests / 2503 assertions
~~~

Migration:

~~~text
database/migrations/2026_09_24_020000_create_context_conversations.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-11-conversation-timeline
git pull --ff-only origin feat/ideal-v1-11-conversation-timeline
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/ConversationKernelTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php \
  tests/Feature/Groups/GroupSpaceCommunicationTest.php \
  tests/Feature/Groups/GroupSpaceGovernanceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story

Continue with the same Alice/Bob/Carol/Diego objects.

- [ ] Open Alice ↔ Bob active Relationship → **Conversation**.
- [ ] Send ordinary messages and a reply; reload and confirm the same order/content remains.
- [ ] Type **“I agree to everything in this chat.”** and confirm Relationship state does not change.
- [ ] Attach an existing Asset from the Relationship Context; confirm no duplicate Asset/file is created.
- [ ] Attach an existing exact Content Evidence Reference; open it and confirm it resolves the pinned historical source.
- [ ] Attempt to use an Asset/evidence reference from another Context; it must be rejected.
- [ ] Open **Timeline** and confirm Relationship lifecycle + messages appear chronologically.
- [ ] Reload Timeline; the same source-derived state reconstructs with no independent Timeline record.
- [ ] Follow **Open source** from a Relationship event and message; each returns to the authoritative source surface.
- [ ] From an unrelated Actor, Conversation and Timeline routes are forbidden.
- [ ] Open a mutable Admission as candidate/reviewer and confirm the same shared Conversation + Timeline surfaces work under Admission authorization.
- [ ] Confirm Admission messages do not approve/finalize Admission or create Membership.
- [ ] Open Maple Housing Office General chat after migration; existing Group chat behavior/replies remain intact.
- [ ] Confirm denied/restricted/archived GroupSpace rules still prevent reading/posting.
- [ ] Open GroupSpace Timeline and confirm Context activity projects without a Group-specific Timeline table.
- [ ] End/cancel a Relationship and confirm its Conversation becomes read-only while historical messages/Timeline remain readable to participants.
- [ ] Mobile/RTL/accessibility/realtime behavior stays in the cumulative later acceptance/polish pass.

### Negative guarantees

- [ ] no `group_space_messages` dual-write store remains;
- [ ] no Timeline persistence table exists;
- [ ] message text cannot perform authoritative acceptance/approval/payment;
- [ ] message references do not copy Assets/Content;
- [ ] Timeline does not leak Content the viewer cannot read.
