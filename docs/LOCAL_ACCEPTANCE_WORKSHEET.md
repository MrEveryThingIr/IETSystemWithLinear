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

Environment selection matters:

- historical office-alpha checkpoints may use `IET_RELEASE_PROFILE=office_alpha`;
- the cumulative publishable Ideal-v1 acceptance must use `IET_RELEASE_PROFILE=full` so Groups, Contexts, Relationships, Planner, Content, finance and the later integrated modules are actually visible and testable.

For the final cumulative release-candidate pass:

~~~text
APP_NAME=IET
IET_RELEASE_PROFILE=full
~~~

After switching to any checkpoint/RC whose `composer.lock` may differ, restore the exact PHP dependencies **before running any Artisan command**:

~~~bash
composer install --no-interaction --prefer-dist
php artisan optimize:clear
~~~

This ordering matters because Artisan boots configured services. For example, a Reverb/Pusher broadcast configuration can legitimately require `pusher/pusher-php-server` from the new lockfile before `optimize:clear` can boot successfully.

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


---

## Checkpoint 12 — Personal Activity / Planner

Remote branch:

~~~text
feat/ideal-v1-12-personal-activity-planner
~~~

Remote kernel checkpoint:

~~~text
SHA: 84dfcf76aca7a3a2df1b9c4c955fde82eb14a06a
CI: 36035381347
Result: 454 tests / 2581 assertions
~~~

Remote final runtime checkpoint:

~~~text
SHA: 8d9f5690ba73daf0f73e03d86981d076ade1cd4e
CI: 36037941878
Result: 458 tests / 2615 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_24_030000_create_planner_kernel.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-12-personal-activity-planner
git pull --ff-only origin feat/ideal-v1-12-personal-activity-planner
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/PlannerKernelTest.php \
  tests/Feature/PlannerExperienceTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Bob personal planning

- [ ] Open **Planner** from the advanced sidebar.
- [ ] Create **Dentist appointment** in Bob's Personal Context for one date/time with reminder offsets.
- [ ] Confirm it appears in **Today**, **List** and the correct **Calendar** day.
- [ ] Create a recurring **Study session** and confirm local clock time remains stable across the Europe/Berlin DST boundary.
- [ ] Open the Plan detail and confirm timezone, participants, rules/reminders and materialized occurrences are understandable.
- [ ] Start an occurrence and confirm actual start time is recorded separately from scheduled time.
- [ ] Complete it later and confirm actual end/completion time is preserved.
- [ ] Attach an existing Personal-Context Asset and exact Evidence Reference; confirm the original artifacts are reused, not copied.
- [ ] Open the Context Timeline and confirm Plan/Occurrence events appear with source links.

### Browser story — Alice ↔ Bob Riverside workdays

- [ ] Open the active Alice ↔ Bob Relationship.
- [ ] Choose **Planner**.
- [ ] Create **Riverside selected workdays** for 2026-09-25, 2026-09-27 and 2026-10-02 at 08:00 for 540 minutes.
- [ ] Confirm Bob is included from active Relationship participation with his explicit role.
- [ ] Confirm the Plan records Relationship provenance.
- [ ] Confirm the Relationship status/events remain unchanged.
- [ ] Confirm no Group Membership, Proposal, Contract, employment, ownership, obligation, Fulfillment acceptance, accounting entry or payment is created by scheduling.
- [ ] Bob can view/participate; an unrelated Actor cannot open the private Relationship Plan.
- [ ] Start/complete one work occurrence and attach same-Context evidence.
- [ ] Confirm the unified Relationship Timeline includes the Planner source events.

### Reminder seam

- [ ] Confirm reminder offsets are stored on the Plan/Schedule Rule.
- [ ] Do not expect push/email/realtime delivery yet; that belongs to Phase 22.
- [ ] Run `php artisan planner:materialize --days=120` locally and confirm recurring horizons extend idempotently.

### Negative guarantees

- [ ] Schedule Rules cannot be silently edited in place; cancel/replace preserves provenance.
- [ ] Occurrence scheduled/provenance fields cannot be mutated directly.
- [ ] cross-Context Assets/Evidence References are rejected.
- [ ] Planner state is not Contract/payment/Fulfillment authority.
- [ ] Timeline remains a projection, not a duplicated Planner transaction store.


---

## Checkpoint 13 — Personal Accounting v1

Remote branch:

~~~text
feat/ideal-v1-13-personal-accounting
~~~

Remote kernel checkpoint:

~~~text
SHA: 81f66c6934c39cbaad26d5363deaad9239777a75
CI: 36041431006
Result: 465 tests / 2669 assertions
~~~

Remote final runtime checkpoint:

~~~text
SHA: c5a45f7d4178637e322855e71318709610e835b8
CI: 36042662030
Result: 469 tests / 2714 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_24_040000_create_accounting_kernel.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-13-personal-accounting
git pull --ff-only origin feat/ideal-v1-13-personal-accounting
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/AccountingKernelTest.php \
  tests/Feature/AccountingExperienceTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Bob personal EUR

- [ ] Open **Accounting** from the advanced sidebar.
- [ ] Create **Bob personal EUR**.
- [ ] Confirm the normal UI does not ask Bob to choose Debit or Credit.
- [ ] Record Opening balance = EUR 1000.00 to Cash.
- [ ] Record Expense = EUR 25.00, category **Work gloves**.
- [ ] Record Income = EUR 100.00, category **Service income**.
- [ ] Confirm monthly summary: Income EUR 100.00, Expense EUR 25.00, Net EUR 75.00.
- [ ] Add Asset Account **Bank**.
- [ ] Transfer EUR 200.00 Cash → Bank.
- [ ] Confirm Cash EUR 875.00, Bank EUR 200.00, total assets EUR 1075.00.
- [ ] Switch period summary to Day/Week/Month/Year and verify values derive from posted history.
- [ ] Reverse the Work gloves entry.
- [ ] Confirm the original entry remains visible/unchanged and a Reversal entry restores its effect.
- [ ] Open the Personal Context Timeline and confirm Accounting activity appears with a source link.

### Accounting integrity

- [ ] Inspect one Journal Entry and confirm line debits equal line credits.
- [ ] Confirm posted Journal Entries cannot be directly edited/deleted.
- [ ] Confirm posted Journal Lines cannot be directly edited/deleted.
- [ ] Confirm an Account from another Ledger cannot be used in the entry.
- [ ] Confirm amounts use exact MonetaryUnit precision and round-trip through integer minor units.
- [ ] Confirm a second Actor cannot select/open Bob's Ledger via URL/query parameter.

### Authority boundary

- [ ] Confirm Bob's personal expense/income does not create a Relationship financial obligation.
- [ ] Confirm Planner occurrence completion does not auto-post Accounting.
- [ ] Confirm Conversation text such as “paid” does not auto-post Accounting.
- [ ] Confirm Content text does not auto-post Accounting.
- [ ] Do not expect shared debt/invoice/settlement behavior until the explicit later Financial Obligation + Settlement phases.


---

## Checkpoint 14 — Proposal + Negotiation

Remote branch:

~~~text
feat/ideal-v1-14-proposal-negotiation
~~~

Remote kernel checkpoint:

~~~text
SHA: 3a30f21af06e478fc269d7db1e4085ce73500133
CI: 36047890895
Result: 474 tests / 2771 assertions
~~~

Remote final runtime checkpoint:

~~~text
SHA: a27a2538161ff36d123eef1bd0f9d9c153298987
CI: 36048778108
Result: 477 tests / 2799 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_24_050000_create_proposal_negotiation_kernel.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-14-proposal-negotiation
git pull --ff-only origin feat/ideal-v1-14-proposal-negotiation
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/ProposalNegotiationKernelTest.php \
  tests/Feature/ProposalExperienceTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Riverside negotiation

- [ ] Alice creates **Riverside construction collaboration** with Bob and Carol.
- [ ] Confirm version 1 terms are an exact sealed published Content revision.
- [ ] Bob accepts version 1.
- [ ] Carol requests changes with a clear note.
- [ ] Confirm version 1 and all version-1 decisions remain unchanged.
- [ ] Carol publishes version 2 with clarified site responsibilities.
- [ ] Confirm Carol is Accepted for version 2 as proposer while Alice/Bob are Pending.
- [ ] Alice accepts version 2; Proposal remains Negotiating while Bob is pending.
- [ ] Bob accepts version 2; Proposal becomes **Accepted proposal**.
- [ ] Confirm version-1 decisions did not count toward version 2.
- [ ] Confirm Negotiation Timeline shows source-linked Proposal events.
- [ ] Confirm terminal Negotiation Conversation becomes read-only.

### Active Relationship handoff

- [ ] Open Alice ↔ Bob active Relationship and choose **Start proposal**.
- [ ] Confirm Bob is prefilled from active participation.
- [ ] Create a paid-work Proposal.
- [ ] Confirm source Relationship provenance is preserved.
- [ ] Confirm Proposal owns a separate Negotiation Context.
- [ ] Confirm Proposal lifecycle does not mutate Relationship lifecycle.

### Negative guarantees

- [ ] Typing “I accept” in Conversation does not create ProposalDecision.
- [ ] Outsiders cannot open Proposal or Negotiation Context.
- [ ] ProposalVersion/ProposalDecision cannot be edited or deleted in place.
- [ ] Accepted/Rejected/Cancelled Proposal accepts no further versions/responses.
- [ ] Accepted Proposal creates no Contract/ContractVersion.
- [ ] Accepted Proposal creates no Commitment/Fulfillment.
- [ ] Accepted Proposal creates no Financial Obligation, Accounting posting or Settlement/payment.


---

## Checkpoint 15 — Contract, ContractVersion and explicit acceptance

Remote branch:

~~~text
feat/ideal-v1-15-contract-version-acceptance
~~~

Remote kernel checkpoint:

~~~text
SHA: cd39e8861844b598e3e9fe81f659b93648e7c4b5
CI: 36054184003
Result: 483 tests / 2869 assertions
~~~

Remote final runtime checkpoint:

~~~text
SHA: df63697b3734bc3a8dfe1b70f58655d4b2c9da72
CI: 36055829092
Result: 487 tests / 2903 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_24_060000_create_contract_version_kernel.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-15-contract-version-acceptance
git pull --ff-only origin feat/ideal-v1-15-contract-version-acceptance
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/ContractKernelTest.php \
  tests/Feature/ContractExperienceTest.php \
  tests/Feature/ProposalExperienceTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — direct Alice/Bob paid work

- [ ] Sign in as Alice and open **Contracts → New contract**.
- [ ] Create **Workshop paid work**.
- [ ] Set Alice role = **employer**, Bob role = **worker**.
- [ ] Enter readable exact terms and the intended effective time.
- [ ] Confirm one sealed Contract terms Content revision is created.
- [ ] Confirm Alice is Accepted as the proposing party and Bob is Pending.
- [ ] In Contract Conversation, Bob types **I accept these terms**.
- [ ] Confirm this text does **not** create ContractAcceptance.
- [ ] Bob uses **Accept ContractVersion**.
- [ ] Confirm version 1 becomes Active when its effective time is due.
- [ ] Confirm Contract Timeline shows source-linked Contract events.
- [ ] Confirm an unrelated Actor cannot open the Contract or Contract Context.

### Browser story — Riverside Proposal → Contract

- [ ] Complete Phase-14 Riverside Proposal acceptance first.
- [ ] Open the Accepted Proposal and choose **Create Contract from Proposal**.
- [ ] Confirm the Contract references the source ProposalVersion.
- [ ] Confirm ContractVersion 1 uses the same exact sealed terms revision rather than copied/editable terms.
- [ ] Confirm project-owner / builder / site-coordinator roles are snapshotted.
- [ ] Confirm Proposal decisions did not become Contract acceptances.
- [ ] Each remaining required party explicitly accepts ContractVersion 1.
- [ ] Confirm the Contract activates only after all required Contract parties accepted the exact version.

### Future amendment

- [ ] On an Active Contract choose **Propose future terms**.
- [ ] Enter revised terms and an effective time in the future.
- [ ] Confirm this creates ContractVersion 2; version 1 stays unchanged/Active.
- [ ] Have every required party explicitly accept version 2.
- [ ] Confirm version 2 becomes **Accepted · awaiting effective time**, while version 1 remains Active.
- [ ] Run `php artisan contracts:activate-due` after the effective instant.
- [ ] Confirm version 1 becomes Superseded with exact `effective_until`.
- [ ] Confirm version 2 becomes Active.
- [ ] Confirm both sealed terms revisions remain readable and unchanged.

### Negative guarantees

- [ ] Proposal acceptance does not count as Contract acceptance.
- [ ] Relationship participation does not count as Contract acceptance.
- [ ] Conversation/Content wording does not count as Contract acceptance.
- [ ] Accepted/effective ContractVersion and ContractAcceptance rows cannot be edited/deleted in place.
- [ ] One accepted ProposalVersion cannot create duplicate Contract authority.
- [ ] Active Contract creates no Commitment/Planner Occurrence automatically.
- [ ] Active Contract creates no Fulfillment automatically.
- [ ] Active Contract creates no Financial Obligation, Accounting posting or Settlement/payment automatically.


---

## Checkpoint 16 — Commitment + Fulfillment

Remote branch:

~~~text
feat/ideal-v1-16-commitment-fulfillment
~~~

Remote kernel checkpoint:

~~~text
SHA: 7a7481a271687bd307746d054749ce2599d87c4d
CI: 36089527651
Result: 492 tests / 2964 assertions
~~~

Remote final runtime checkpoint:

~~~text
SHA: 67b986cce96f7d6e72db811045a9c1a01c581ddb
CI: 36093668972
Result: 495 tests / 2989 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_25_000000_create_commitment_fulfillment_kernel.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-16-commitment-fulfillment
git pull --ff-only origin feat/ideal-v1-16-commitment-fulfillment
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/CommitmentFulfillmentKernelTest.php \
  tests/Feature/CommitmentFulfillmentExperienceTest.php \
  tests/Feature/ContractExperienceTest.php \
  tests/Feature/PlannerExperienceTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Riverside construction workday

- [ ] Start from the Active Alice ↔ Bob Riverside ContractVersion.
- [ ] On Contract detail choose **New commitment**.
- [ ] Create **Riverside construction workday**.
- [ ] Set Bob as responsible Actor and Alice as beneficiary/reviewer.
- [ ] Set required quantity = **1**, unit = **day**.
- [ ] Confirm the Commitment records the exact active ContractVersion.
- [ ] Amend/activate the Contract later and confirm this Commitment still points to the original governing ContractVersion.

### Planner binding

- [ ] Open the Commitment.
- [ ] Create a Commitment Plan for the selected Riverside workday.
- [ ] Confirm the Plan and Occurrence record Commitment provenance.
- [ ] As Bob, start the occurrence.
- [ ] Complete it after actual work.
- [ ] Attach same-Context evidence if needed.
- [ ] Confirm Planner completion alone creates no Fulfillment.

### Explicit Fulfillment

- [ ] As Bob, choose the completed occurrence and **Submit Fulfillment**.
- [ ] Set performed quantity = **1** and add a clear note.
- [ ] Confirm actual start/end/duration are copied from the occurrence as evidence of what happened.
- [ ] Confirm exact occurrence Assets/Evidence References are reused rather than copied.
- [ ] Confirm status = **Submitted** and accepted progress remains 0.

### Explicit review

- [ ] As Alice, review the submitted Fulfillment.
- [ ] Choose **Accept**.
- [ ] Confirm accepted quantity becomes 1 and remaining quantity becomes 0.
- [ ] Confirm the original Fulfillment/review remains immutable.

### Clarification / correction

- [ ] In a separate Fulfillment, choose **Request clarification**.
- [ ] Confirm the original is not edited.
- [ ] Submit a correction/replacement Fulfillment.
- [ ] Confirm correction lineage points back to the prior Fulfillment.
- [ ] Review the replacement independently.

### Dispute

- [ ] Open a dispute against an accepted Fulfillment.
- [ ] Confirm accepted Commitment progress temporarily excludes the disputed quantity.
- [ ] Resolve explicitly as accepted.
- [ ] Confirm accepted progress returns.
- [ ] Repeat locally with rejected resolution if useful.

### Timeline and negative guarantees

- [ ] Confirm Commitment/Fulfillment lifecycle events appear in the Contract Context Timeline with source links.
- [ ] Confirm an unrelated Actor cannot open the Commitment/Fulfillment.
- [ ] Confirm cross-Context Asset/Evidence attachment is rejected.
- [ ] Confirm Planner completion does not auto-submit Fulfillment.
- [ ] Confirm Fulfillment submission does not auto-accept performance.
- [ ] Confirm accepted Fulfillment creates no Financial Obligation.
- [ ] Confirm accepted Fulfillment creates no Accounting JournalEntry.
- [ ] Confirm accepted Fulfillment creates no Settlement/payment truth.


---

## Checkpoint 17 — Financial Obligation + Settlement bridge

Remote branch:

~~~text
feat/ideal-v1-17-financial-obligation-settlement
~~~

Remote kernel checkpoint:

~~~text
SHA: 90e0e0ec0033954bacdaca944fee9a2efe2e9095
CI: 36114145008
Result: 500 tests / 3036 assertions
~~~

Remote final runtime checkpoint:

~~~text
SHA: 47bae48638345a807623ceb7f1ae44866d09d9e6
CI: 36115933156
Result: 502 tests / 3065 assertions; Pint/PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_25_010000_create_financial_obligation_settlement_bridge.php
~~~

### Local sync

~~~bash
git fetch origin
git switch feat/ideal-v1-17-financial-obligation-settlement
git pull --ff-only origin feat/ideal-v1-17-financial-obligation-settlement
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/FinancialObligationSettlementKernelTest.php \
  tests/Feature/FinancialObligationSettlementExperienceTest.php \
  tests/Feature/CommitmentFulfillmentExperienceTest.php \
  tests/Feature/ConversationTimelineExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Riverside earned / paid / outstanding

- [ ] Use an Active Riverside Contract with Alice as beneficiary/reviewer and Bob as responsible worker.
- [ ] Create/perform/review three workday Fulfillments and explicitly Accept all three.
- [ ] On each accepted Fulfillment choose **Recognize financial obligation**.
- [ ] Enter 1,500,000 IRR for each workday.
- [ ] Confirm each obligation preserves the exact Fulfillment and ContractVersion.
- [ ] Confirm Contract financial summary derives earned = 4,500,000 IRR and outstanding = 4,500,000 IRR.
- [ ] As Alice, explicitly post each Financial Obligation to Alice's Personal Accounting.
- [ ] As Bob, explicitly post the same source obligations to Bob's own Personal Accounting.
- [ ] Confirm balanced JournalEntries exist in separate Ledgers and retries do not duplicate them.
- [ ] Propose a 3,000,000 IRR Settlement claim with payment time/method/reference.
- [ ] Confirm the proposer cannot self-confirm the Settlement.
- [ ] As the counterparty, explicitly confirm it.
- [ ] Confirm Contract summary derives paid = 3,000,000 IRR and outstanding = 1,500,000 IRR.
- [ ] Explicitly post Settlement Accounting for Alice and Bob.
- [ ] Confirm each Actor's Ledger gets one idempotent balanced Settlement JournalEntry.
- [ ] Open Contract Timeline and confirm bilateral financial events link to the Financial Obligation page.

### Dispute behavior

- [ ] With one accepted but unpaid workday obligation, dispute its source Fulfillment.
- [ ] Confirm Contract summary moves its remaining amount from earned/outstanding into disputed.
- [ ] Attempt to confirm a pending Settlement for that obligation and confirm it is blocked.
- [ ] Resolve the Fulfillment dispute back to accepted.
- [ ] Confirm the immutable Financial Obligation remains the same and its remaining amount returns to outstanding.

### Privacy / multi-party boundary

- [ ] Add Carol as a Contract party who is not debtor/creditor of Bob's work obligation.
- [ ] Confirm Carol may view the Contract if authorized.
- [ ] Confirm Carol cannot open the Financial Obligation URL.
- [ ] Confirm Carol's Contract Timeline excludes that bilateral financial event.
- [ ] Confirm Carol cannot post Accounting or respond to its Settlement.

### Negative guarantees

- [ ] Fulfillment submission does not create a Financial Obligation.
- [ ] Fulfillment acceptance alone does not create a Financial Obligation.
- [ ] Contract activation does not create a Financial Obligation.
- [ ] Planner completion does not create a Financial Obligation.
- [ ] Conversation/Content text such as “paid” does not create Settlement or Accounting.
- [ ] Settlement proposal alone does not count as paid.
- [ ] Settlement confirmation does not silently post Accounting.
- [ ] No mutable owed/paid/outstanding balance column is used; values derive from immutable sources.


---

## Checkpoint 18 — Journey / Relationship / Domain Blueprints

Remote branch:

~~~text
feat/ideal-v1-18-domain-blueprints
~~~

Remote runtime checkpoint:

~~~text
SHA: e4c9cb4cce39a1e0a37bad6ae72e97b6ab5feb77
CI: 36119387963
Result: 510 tests / 3138 assertions; Pint 638 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_25_020000_create_domain_blueprints.php
~~~

### Local sync

After Phase 18 is integrated, prefer validating the cumulative integration line rather than checking out this historical feature branch:

~~~bash
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/DomainBlueprintKernelTest.php \
  tests/Feature/DomainBlueprintExperienceTest.php \
  tests/Feature/SystemManualContentTest.php \
  tests/Feature/RelationshipExperienceTest.php \
  tests/Feature/PlannerExperienceTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story — Journeys catalog

- [ ] Sign in as an active verified user.
- [ ] Open **Journeys** from the application navigation.
- [ ] Confirm six recipes are visible: Simple Sale, Rental, Service Job, Employment / Paid Work, Construction Partnership and Personal Activity.
- [ ] Confirm each card shows its recipe version, recommended capabilities and useful Content templates.
- [ ] Confirm the boundary copy makes clear that a recipe does not itself create agreement, performance, money, ownership or other authority.

### Service Job journey

- [ ] Open **Service Job**.
- [ ] Confirm Relationship creation is guided with client / service-provider terminology and a service purpose hint.
- [ ] Change any editable values that should differ; guidance must not lock user-owned details.
- [ ] Select a real purpose Concept and Bob as participant.
- [ ] Create the Relationship.
- [ ] Confirm the Relationship page shows **Service Job** and the exact recipe version as source provenance.
- [ ] Confirm the normal Relationship invitation/participation lifecycle still applies.
- [ ] Confirm recommended Proposal/Contract/Planner/etc. capabilities are not auto-created.

### Personal Activity journey

- [ ] Open **Personal Activity**.
- [ ] Confirm Planner creation receives safe defaults such as one-time frequency and 60-minute duration.
- [ ] Enter a real activity, date/time and reminders.
- [ ] Save the Plan.
- [ ] Confirm the Plan page shows **Personal Activity** and the exact recipe version.
- [ ] Confirm no Contract, Commitment, Financial Obligation, Settlement or JournalEntry was created merely from the recipe.

### Version provenance

- [ ] Inspect an existing Relationship/Plan created from a Blueprint version.
- [ ] After a later system Blueprint revision is introduced in a future checkpoint, confirm the existing object still names its original exact version.
- [ ] Confirm published Blueprint versions are never edited/deleted in place.

### Authorization / negative guarantees

- [ ] Confirm knowing a Blueprint slug/URL does not grant access to another user's Relationship or Plan.
- [ ] Confirm a Personal Activity Blueprint cannot be submitted through Relationship creation.
- [ ] Confirm a Relationship Blueprint cannot be submitted as a Personal Activity Plan recipe.
- [ ] Confirm a Blueprint does not grant Content/Contract/financial/accounting permissions.
- [ ] Confirm no recipe executes arbitrary PHP, Blade, JavaScript, SQL or CSS from stored configuration.

### System Manual

- [ ] Open the IET System Manual.
- [ ] Confirm Chapter 23 is **Journeys and Domain Blueprints**.
- [ ] Confirm Help/topic routing for `journeys` opens Chapter 23 at **How to use it**.
- [ ] Confirm the chapter teaches both Service Job and Personal Activity examples and clearly states the authority boundaries.


---

## Checkpoint 19 — Need / Offer Matching

Remote branch:

~~~text
feat/ideal-v1-19-need-offer-matching
~~~

Remote runtime checkpoint:

~~~text
SHA: 532901b4e11d78cb5d848ca4bb6039632c62f3a6
CI: 36124849892
Result: 517 tests / 3192 assertions; Pint 645 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
database/migrations/2026_09_25_030000_add_matching_provenance_to_relationships.php
~~~

### Local sync

After Phase 19 integration, validate the cumulative integration line:

~~~bash
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/IntentMatchingKernelTest.php \
  tests/Feature/IntentMatchingExperienceTest.php \
  tests/Feature/RelationshipExperienceTest.php \
  tests/Feature/SystemManualContentTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Alice construction Need → Bob Service Offer

- [ ] As Alice, record an Active authenticated-visible Service Need for the construction Concept.
- [ ] Add meaningful explicit constraints such as quantity/unit, Riverside location, dates/time, currency/cash range.
- [ ] As Bob, record the opposite Service Offer for the same canonical Concept with compatible constraints.
- [ ] Return as Alice and open **Needs, offers & services**.
- [ ] On Alice's own Need choose **Find matches**.
- [ ] Confirm Bob's Offer appears.
- [ ] Confirm **Why this candidate appears** lists only real aligned dimensions.
- [ ] Confirm a wrong-location or incompatible cash-range Offer does not appear.

### Alice Capital Need → Carol Capital Offer

- [ ] Record Alice's Capital Need with explicit currency/range.
- [ ] Record Carol's opposite Capital Offer with overlapping currency/range.
- [ ] Confirm Carol appears as a candidate.
- [ ] Record a same-Concept Offer with a different currency and confirm it is excluded when both sides specify currency.

### Privacy

- [ ] Keep Bob's Profile private while Bob's Offer is explicitly authenticated-visible.
- [ ] Confirm Alice can discover the Offer.
- [ ] Confirm the Match page does not reveal Bob's private username/Profile identity.
- [ ] Confirm a private Intent does not appear at all.
- [ ] Confirm an unrelated user cannot open Alice's owner-only Match page.

### Relationship handoff

- [ ] From Bob's candidate choose **Start relationship**.
- [ ] Confirm the purpose is sourced from the Intent Concept.
- [ ] Confirm the counterparty is resolved server-side rather than typed/leaked into the form.
- [ ] Submit the Relationship.
- [ ] Confirm status is **Proposed**.
- [ ] Confirm the Relationship stores both the originating Intent and matched counterpart Intent.
- [ ] Confirm Bob must explicitly accept before the Relationship becomes Active.
- [ ] Confirm Proposal creation remains downstream of an Active Relationship.

### Tamper / stale-candidate behavior

- [ ] Open a stored Match handoff URL after pausing/closing the counterpart Intent; confirm Relationship creation is rejected.
- [ ] Replace the candidate UUID with an incompatible Intent; confirm rejection.
- [ ] Confirm Concept hierarchy alone does not make a different Concept substitutable.

### Negative guarantees

- [ ] Opening Find matches creates no Match row, Relationship, Proposal or Contract.
- [ ] Starting a matched Relationship creates no Proposal/Contract automatically.
- [ ] Matching creates no Commitment/Fulfillment.
- [ ] Matching creates no Financial Obligation/Settlement.
- [ ] Matching creates no JournalEntry or payment truth.
- [ ] The aligned-dimensions count is presented as explanation/order, not a universal quality/trust score.

### System Manual

- [ ] Confirm Chapter 24 is **Need / Offer Matching**.
- [ ] Confirm contextual Help on the Match page routes to Chapter 24.
- [ ] Confirm the chapter explains the Match → Relationship consent → optional Proposal chain and privacy boundary.


---

## Checkpoint 20 — Group / Community Composition

Remote branch:

~~~text
feat/ideal-v1-20-group-community-composition
~~~

Remote runtime checkpoint:

~~~text
SHA: 6782bdaeb2a732ec2f9ae8f72c76dc5f311d6f17
CI: 36126321777
Result: 520 tests / 3219 assertions; Pint 651 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
None. Phase 20 is intentionally a composition/read/UI layer over existing domain storage.
~~~

### Local sync

After Phase 20 integration:

~~~bash
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/Groups/GroupCommunityCompositionTest.php \
  tests/Feature/Groups/GroupSpaceCommunicationTest.php \
  tests/Feature/Groups/GroupSpaceGovernanceTest.php \
  tests/Feature/SystemManualContentTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Maple Housing Office community

- [ ] Sign in as an active Maple Housing Office member.
- [ ] Open **Groups** and confirm **Open** goes to the Group **Community** page.
- [ ] Confirm Community and **Governance & settings** are visibly distinct purposes.
- [ ] Confirm **General** appears and links to Conversation, Content, Plans & activities and Timeline.
- [ ] Confirm restricted Spaces appear only for users who already have Space view permission.

### Content composition

- [ ] Publish an article/report in General Content.
- [ ] Confirm it appears in **Recent Content** without creating a second Group-post/article record.
- [ ] Open it and confirm it is the same Content UUID/Context record used by the normal Reader/Studio.

### People, Needs & Offers

- [ ] Confirm active Group participants and their Group roles appear.
- [ ] Give Bob an authenticated-visible Service Offer and confirm it appears.
- [ ] Make Bob's Profile private while keeping the explicit Intent visible; confirm the Intent can remain visible without leaking the private Profile identity.
- [ ] Mark an Intent private and confirm it disappears.

### Plans / activity

- [ ] Create a Plan in General's Context.
- [ ] Confirm it appears in Community and opens the normal Planner record.
- [ ] Create a Plan in a restricted Space inaccessible to an ordinary member; confirm it does not appear for that member.

### Relationships / projects

- [ ] Create a Relationship involving Alice and Bob where the current viewer participates and Bob is an active Group member; confirm it appears.
- [ ] Create an unrelated Relationship between other actors; confirm Group membership alone does not expose it.

### Submissions

- [ ] Configure a Space Context interaction/review flow.
- [ ] Submit a record.
- [ ] Confirm the review card appears only for a viewer with Context review authority.
- [ ] Confirm an ordinary Group member without review authority sees no Submission data.

### Negative guarantees

- [ ] Confirm Community creates no new Group-specific Content/Plan/Intent/Relationship/Submission tables or rows.
- [ ] Confirm Group membership does not reveal restricted Space data.
- [ ] Confirm Group membership does not reveal private Profile or Intent data.
- [ ] Confirm Group membership does not reveal unrelated Relationships.
- [ ] Confirm Group membership does not reveal non-reviewable Submissions.

### System Manual

- [ ] Confirm Chapter 25 is **Group / Community Composition**.
- [ ] Confirm Help on the Community page opens Chapter 25 at **How to use it**.
- [ ] Confirm it teaches the difference between Community usage and Group governance/settings.


---

## Checkpoint 21 — Home / Today

Remote branch:

~~~text
feat/ideal-v1-21-home-today
~~~

Remote runtime checkpoint:

~~~text
SHA: d0834e5a72545d558d074deb495fd7009daa96ff
CI: 36128745672
Result: 524 tests / 3244 assertions; Pint 660 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

Migration:

~~~text
None. Today is intentionally a derived operating view.
~~~

### Local sync

After Phase 21 integration:

~~~bash
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/HomeTodayExperienceTest.php \
  tests/Feature/LocalizationTest.php \
  tests/Feature/ReleaseExperienceTest.php \
  tests/Feature/SystemManualContentTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### What should I do today?

- [ ] Create a personal or Context Plan with an occurrence today.
- [ ] Open Dashboard/Today.
- [ ] Confirm the occurrence appears with local start/end time.
- [ ] Open it and confirm the normal Planner record is authoritative.

### Waiting on me

Exercise whichever flows are available in the cumulative story:

- [ ] proposed Relationship where current user is invited;
- [ ] negotiating Proposal whose current version needs this user's decision;
- [ ] proposed ContractVersion requiring this user's acceptance;
- [ ] submitted Fulfillment where this user is beneficiary/reviewer;
- [ ] pending Settlement claim where this user may respond;
- [ ] submitted interaction in a Context this user may review.

Confirm each Today item opens the real source record.

### Waiting on others

- [ ] Create a proposed Relationship initiated by current user and confirm it appears as waiting on the invited party.
- [ ] After current user decides on a Proposal version, confirm it remains here while another required party is pending.
- [ ] After current user accepts a Contract version, confirm it remains here while another required party is pending.
- [ ] Submit a Fulfillment as obligor and confirm it remains here until beneficiary review.
- [ ] Propose a Settlement and confirm it remains here until counterparty response.

### Money

- [ ] Post income and expense today in Personal Accounting; confirm Today shows the exact ledger-derived values.
- [ ] Confirm no Contract/FinancialObligation appears as accounting income/expense until explicitly posted.
- [ ] Create EUR and USD obligations and confirm Today shows separate monetary-unit buckets.
- [ ] Confirm receivable/payable direction is correct for creditor/debtor.
- [ ] Confirm confirmed Settlement changes paid/outstanding without rewriting the original obligation.

### Current context

- [ ] Confirm current user's active Needs/Offers appear.
- [ ] Confirm active Relationships appear.
- [ ] Confirm active Group memberships link to Group Community.
- [ ] Confirm private/unrelated records do not leak merely because Today is an aggregate view.

### Recent activity

- [ ] Perform events in personal, GroupSpace and Relationship/Proposal/Contract Contexts.
- [ ] Confirm authorized recent events appear.
- [ ] Confirm inaccessible Context activity does not appear.
- [ ] Confirm the page does not label recent events as unread/read notifications.

### Release/localization edges

- [ ] Switch locale among English, Persian, Arabic and Chinese and confirm Today labels change.
- [ ] Confirm Persian/Arabic remain RTL.
- [ ] Under office-alpha, confirm hidden advanced modules are not exposed through Today quick links.
- [ ] Verify an account lacking a primary Actor receives a safe reduced Today state instead of an exception.

### Negative guarantees

- [ ] Opening Today creates no Plan/Relationship/Proposal/Contract/Fulfillment/Settlement/Submission/JournalEntry/FinancialObligation.
- [ ] Today does not create an inbox/task table.
- [ ] Today does not create notification read/unread state.
- [ ] Today does not combine different currencies.

### System Manual

- [ ] Confirm Chapter 26 is **Home / Today Operating View**.
- [ ] Confirm contextual Help from Dashboard/Today opens Chapter 26 at **How to use it**.


---

## Checkpoint 22 — Realtime + Notifications

Remote branch:

```text
feat/ideal-v1-22-realtime-notifications
```

Apply the notification migration on the continuing database; do not use `migrate:fresh`.

### Local sync and focused gate

```bash
git fetch origin
git switch feat/ideal-v1-22-realtime-notifications
git pull --ff-only origin feat/ideal-v1-22-realtime-notifications

php artisan optimize:clear
php artisan migrate --force

php artisan test --compact \
  tests/Feature/NotificationKernelTest.php \
  tests/Feature/NotificationExperienceTest.php \
  tests/Feature/DomainNotificationProjectionTest.php \
  tests/Feature/PlanReminderNotificationTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
```

### Browser/realtime story

- [ ] Alice opens Notifications and sees durable unread/all state.
- [ ] Bob sends a message in an authorized Alice/Bob Context; Alice receives one durable notification.
- [ ] With Reverb + queue worker running, Alice's open Notifications page refreshes without a full browser reload.
- [ ] Stop Reverb, produce another notification, reload Alice's page and confirm the notification is still present.
- [ ] Mark one notification read; reload and confirm read state persists.
- [ ] Mark all read; unread count becomes zero and stays zero after reload.
- [ ] An unrelated Carol session cannot subscribe to Alice's private user channel or open Alice-only targets.
- [ ] Create a due Planner reminder; repeated emitter runs do not duplicate the same logical reminder.
- [ ] Trigger a Relationship/Proposal/Contract/Fulfillment/Settlement lifecycle event and confirm only intended counterparties receive attention.
- [ ] Submit a structured interaction; only authorized reviewers receive the review notification.
- [ ] Finalize an Evaluation; the submitter receives durable attention without gaining reviewer-only controls.
- [ ] Force/observe a rolled-back authoritative transaction in automated proof; no ghost notification survives.

### Realtime local processes

```bash
# terminal 1
php artisan reverb:start

# terminal 2
php artisan queue:work --queue=notifications,default

# terminal 3
npm run dev
```

Set `BROADCAST_CONNECTION=reverb` and the documented REVERB/VITE_REVERB variables for this realtime check.


---

## Final Publishable Ideal-v1 — Cumulative 0→100 Release Acceptance

This is the authoritative owner browser pass for the first publishable Ideal-v1. Run it only after the hardening branch is integrated into `integration/ideal-v1` and the exact integration SHA is green. Record that immutable SHA and CI run here before testing.

~~~text
Release-candidate SHA: <record after integration>
CI run: <record after integration>
Release profile: full
~~~

### A. Exact local candidate

~~~bash
cd /c/laragon/www/EveryThing

git status --short
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD

# Restore the exact PHP dependency graph from composer.lock before Artisan boots.
composer install --no-interaction --prefer-dist
composer show pusher/pusher-php-server

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

npm ci
npm run build

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
composer audit --locked --no-interaction
npm audit --audit-level=high
~~~

Set `IET_RELEASE_PROFILE=full` for this pass. Never use `migrate:fresh` against the continuing acceptance database.

If an earlier RC already failed locally in `2026_09_24_020000_create_context_conversations` with MySQL error 1059 (identifier too long), **do not manually drop tables and do not run `migrate:fresh`**. Switch to the corrected RC and rerun `php artisan migrate --force`. The corrected migration recognizes this exact unrecorded partial-DDL attempt and recreates the incomplete conversation tables only when they are empty. If it reports that one contains data, stop and inspect that state before any cleanup.


### B. Runtime processes

For the realtime portion, use the configured Reverb environment and run:

~~~bash
# terminal 1
php artisan serve

# terminal 2
php artisan queue:work database --queue=notifications,default --tries=3 --timeout=180

# terminal 3
php artisan schedule:work

# terminal 4, when BROADCAST_CONNECTION=reverb
php artisan reverb:start

# terminal 5, for local frontend development when not using the production build
npm run dev
~~~

The database remains authoritative if Reverb is stopped; reload must reconstruct notification state.

### C. Canonical personas and continuity

Use the same story world throughout instead of creating unrelated throwaway examples:

- Diego — platform administrator / inviter;
- Alice — owner/client with a Riverside property/construction need;
- Bob — electrician/service provider;
- Carol — collaborator/capital participant/reviewer where appropriate;
- Maple Housing Office — reusable Group/community example.

Reuse the same Concepts, Intents, Relationships, Content and financial story as they move downstream.

### D. Access → identity → Profile → Intent

- [ ] Diego creates a reserved-email standalone Access Invitation for Alice and confirms it is constrained to one use.
- [ ] Alice opens the private welcome link, registers with the reserved email, verifies email and reaches Get Started.
- [ ] The consumed reserved link cannot register another account.
- [ ] An unreserved bounded invitation can still support its configured multi-use behavior.
- [ ] Alice creates/edits her Profile without exposing account email as a public Profile field.
- [ ] Alice records a Construction/Electrical Need using the guided Intent journey, including subject, arrangement, location, timing and optional value/exchange preferences.
- [ ] Saving returns to the Directory and visibly highlights the created Intent.
- [ ] Editing from Profile preserves the same guided subject/arrangement/value facets.
- [ ] A private Intent is absent for unrelated viewers; an explicitly authenticated-visible Intent remains discoverable even when Profile identity is private.
- [ ] Pagination/filtering still finds an authorized Intent beyond a large set of hidden records.

### E. Discovery → Relationship → Conversation

- [ ] Bob records the matching Service Offer.
- [ ] Alice opens Find matches and sees only policy-authorized compatible candidates with explainable alignment reasons.
- [ ] Matching creates no Relationship, Proposal, Contract, Commitment or money truth by itself.
- [ ] Alice starts a Relationship from Bob's candidate; exact originating/matched Intent provenance is retained.
- [ ] Bob must explicitly accept before the Relationship becomes active/writable.
- [ ] Alice/Bob can use the Relationship Context conversation after activation.
- [ ] Carol, when unrelated, cannot open the Relationship or its private Context.
- [ ] Ending the Relationship preserves history and makes terminal collaboration read-only where specified.

### F. Proposal → Contract → Commitment → Planner

- [ ] Alice/Bob negotiate a Proposal through explicit versions/decisions.
- [ ] The Proposal alone creates no active Contract.
- [ ] Create a Contract from the agreed proposal and require explicit party acceptance of the exact Contract version.
- [ ] Accepted/effective Contract activation is visible and historically versioned.
- [ ] Create a Commitment from the Contract without duplicating Contract authority.
- [ ] Bind the Commitment to a Plan and materialize occurrences.
- [ ] Today shows the authoritative Planner occurrence and waiting-on-me/others states rather than creating a parallel task record.

### G. Fulfillment → financial obligation → settlement → accounting

- [ ] Bob submits Fulfillment evidence for a committed work occurrence.
- [ ] Alice accepts/reviews the Fulfillment.
- [ ] Confirm the accepted economic event creates/permits the intended immutable Financial Obligation through the explicit bridge, not from casual text or Planner completion.
- [ ] Bob/Alice propose and confirm a Settlement; pending claims do not count as paid.
- [ ] Confirm paid/outstanding amounts derive from immutable obligation + confirmed settlement evidence.
- [ ] Post the confirmed economic event into Personal Accounting only through the explicit accounting action.
- [ ] Confirm balanced JournalEntry/JournalLine truth and no silent accounting entry from Contract/Fulfillment/Settlement alone.
- [ ] Exercise dispute behavior and confirm disputed amounts/status derive correctly without rewriting historical source evidence.
- [ ] A Contract party who is not debtor/creditor cannot access bilateral financial details merely because they can view the Contract.

### H. Group → Admission → Agreement → Membership → Community

- [ ] Diego/authorized Group manager creates a Group invitation for an existing verified account.
- [ ] The candidate follows Invitation → Admission → exact Agreement-version acceptance → submission/review/clarification if configured → approval → Membership.
- [ ] Admission alone grants no ordinary Group/Space access.
- [ ] Finalized Membership grants only the authorized Group/Space capabilities.
- [ ] Ownership/role operations preserve the existing race/integrity protections.
- [ ] Open Maple Housing Office Community and confirm Content, people, Plans, visible Needs/Offers and authorized Relationships compose existing kernels rather than duplicate Group-specific truth.
- [ ] Restricted Spaces remain hidden from unauthorized members.

### I. Content → publication → evidence → placement

- [ ] In an authorized Context create Content from a Blueprint and edit structured fields/blocks/assets/presentation.
- [ ] Publish an immutable revision and open its Reader.
- [ ] Create an exact evidence reference to a revision/field/block/asset target.
- [ ] Publish a newer revision and confirm the historical evidence reference still resolves the original sealed target.
- [ ] Present published Content into another authorized Context using ContentPlacement.
- [ ] Target-only readers gain the intended published read access but no source Context/Studio/transitive resharing authority.
- [ ] Remove and re-add placement; provenance remains coherent rather than duplicating identities.
- [ ] Private Assets remain application-authorized and are never converted to public storage for convenience.

### J. Structured interaction → Submission → Evaluation

- [ ] Configure/use an InteractionDefinition in an authorized Context.
- [ ] Bob submits structured responses/evidence.
- [ ] Only authorized reviewers see the review queue and response details.
- [ ] Carol, when assigned/authorized as reviewer, records an Evaluation.
- [ ] The submitter sees the resulting state/notification but never gains reviewer-only mutation controls.
- [ ] Annotation/conversation text remains collaboration evidence and does not silently become Evaluation or authoritative approval.

### K. Notifications → realtime → reconstruction

- [ ] Trigger Relationship/Proposal/Contract/Fulfillment/Settlement/Submission events and confirm intended recipients receive one durable notification.
- [ ] Mark individual/all notifications read and confirm the state survives reload.
- [ ] Trigger a due Planner reminder more than once and confirm logical idempotency.
- [ ] With Reverb running, an open inbox/navigation updates without a full reload.
- [ ] Stop Reverb, create another notification, reload and confirm durable database state still reconstructs it.
- [ ] An unrelated user cannot subscribe to another user's private channel or open the notification target.
- [ ] A rolled-back authoritative transaction leaves no ghost durable notification.

### L. Cross-cutting release edges

- [ ] Dashboard/Today, Directory, Groups, Content, Relationships, Planner, finance and Notifications expose no unauthorized records when IDs/UUIDs are tampered with.
- [ ] English/Persian/Arabic/Chinese navigation still renders; Persian/Arabic remain RTL.
- [ ] Mobile/responsive smoke covers onboarding, Directory, Profile, Community, Reader, Planner/Today and Notifications.
- [ ] Keyboard/focus smoke covers primary forms, dialogs, pagination and highlighted Intent handoff.
- [ ] `/up` responds successfully.
- [ ] `php artisan schedule:list` contains Agreement activation, Contract activation, Planner horizon, notification outbox/reminders and queue pruning.
- [ ] `php artisan queue:failed` shows no unexplained release-test failures.
- [ ] No plaintext invitation/reset tokens, credentials or private uploaded content appear in logs.
- [ ] Every browser defect found here is reproduced by an automated regression before correction.

### M. Acceptance decision

Record:

~~~text
Candidate SHA:
CI run:
Local full test result:
Browser acceptance date:
Browser tester:
Defects found:
Correction SHAs:
Final accepted SHA:
Production-infrastructure evidence pending/complete:
~~~

The stable release may be tagged only from the exact accepted SHA after all release-blocking browser findings are regression-tested and closed. Infrastructure-specific production evidence (real mail, supervised processes, backups/restore and monitoring) must be recorded honestly when that infrastructure exists; it cannot be inferred from repository CI.


---

## Final checkpoint — First publication experience hardening

Remote branch:

~~~text
codex/release-first-publication-hardening
~~~

Release PR:

~~~text
PR #28 → release/ideal-v1-rc-6
~~~

### Local sync

~~~bash
git fetch origin
git switch codex/release-first-publication-hardening
git pull --ff-only origin codex/release-first-publication-hardening
git status --short
git rev-parse --short HEAD

composer install --no-interaction --prefer-dist
php artisan optimize:clear

php artisan migrate --force
php artisan migrate:status

php artisan test --compact tests/Feature/FirstPublicationExperienceTest.php
php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm ci
npm audit --audit-level=high
npm run build
composer audit
~~~

For a disposable local database only, the complete bootstrap smoke is:

~~~bash
php artisan migrate:fresh --seed --no-interaction
~~~

Never run that destructive command on the continuing acceptance or production database.

### Browser story — first verified account

- [ ] Create/register a fresh invited User and verify the email.
- [ ] Open Personal Accounting. A personal ledger exists with Cash, Opening balance, General income and General expense accounts.
- [ ] Confirm there is **no** fabricated JournalEntry and the current balance is still derived from explicit records only.
- [ ] Re-open the verification URL / repeat provisioning paths and confirm no duplicate main ledger or system accounts are created.
- [ ] Open Profile → Date & time preferences; change the default currency to another supported monetary unit.
- [ ] Confirm the new unit can be used for a personal ledger while existing ledgers/history remain unchanged.
- [ ] Confirm all four supported UI locales render the preference labels without raw translation keys.

### Browser story — persistent temporal header

- [ ] Navigate between Dashboard, Profile, Planner, Accounting and Group/Content surfaces.
- [ ] On desktop width, confirm the header shows a live localized clock/date using the selected timezone/calendar.
- [ ] Confirm seconds update without a page reload.
- [ ] Confirm the small guidance message rotates locally and no external news/tracking request is made.
- [ ] Switch English ↔ Persian ↔ Arabic ↔ Simplified Chinese and confirm text direction/layout remain usable.

### Browser story — fractal Planner

- [ ] Open Planner → Calendar.
- [ ] Open the year level; choose a month.
- [ ] In month view, choose a day.
- [ ] The day view shows all 24 hourly rows and the occurrences assigned to their local hours.
- [ ] Choose **add to day**; the Plan form opens with that date prefilled.
- [ ] Navigate previous/next year/month/day and confirm the correct period is shown.
- [ ] With enough unrelated inaccessible occurrences present, confirm the authorized user's legitimate occurrences are not truncated out of the visible result set.
- [ ] Confirm Planner activity still creates no Contract, employment, Financial Obligation, accounting posting or payment truth.

### Seed/bootstrap smoke

- [ ] On a disposable local database, run `php artisan migrate:fresh --seed --no-interaction`.
- [ ] Confirm the bootstrap User, Actor and Superadmin grant are created.
- [ ] Confirm personal accounting defaults seed successfully with UUID/model hooks intact.
- [ ] Run the seeder again where supported and confirm idempotent domain state rather than duplicate system records.

### Publication boundary

This checkpoint is publishability/UX hardening. It does not introduce a second accounting system, a generic JSON event store, implicit financial transactions, or a new authority layer. Existing Context/Action/domain-event boundaries remain authoritative.
