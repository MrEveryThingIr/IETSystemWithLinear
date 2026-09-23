# IET Current State

## Snapshot

Current implementation baseline:

- Branch: `feat/phase-07-submission-evaluation`.
- Phase 1 — Invitation/registration/admission journey: complete.
- Phase 2 — Delivery and operations baseline: complete at the provider-neutral baseline.
- Phase 3 — Concept Kernel: complete.
- Phase 4 — Actor/Party + progressive Profile: complete and human-owner accepted.
- Phase 5 — Generic Content Context: complete and human-owner accepted on 2026-09-22.
- Phase 6 — Content Blueprints and unified productized authoring: **complete and human-owner accepted for roadmap progression on 2026-09-22**.
- Frozen Phase 6 runtime candidate: `ad07445b16a708b4efd67461f5cef12201ffa8b1`.
- GitHub Actions run `35739828516` on that exact runtime commit:
  - PHPUnit: **364 passed / 1934 assertions**;
  - PHPStan: no errors;
  - Pint: **258 files passed**;
  - Vite production build: passed;
  - Phase 6 migrations / scheduler / database-queue smoke: passed;
  - SQLite backup → restore smoke: passed;
  - npm audit: 0 vulnerabilities;
  - Composer security audit: clean.
- Local closure on synchronized HEAD `b33bdcf` applied all three Phase 6 migrations; passed **20 focused tests / 121 assertions**, **364 full tests / 1934 assertions**, PHPStan, Vite build, `git diff --check`, and a clean working tree after restoring unrelated whole-repository Pint rewrites.
- Human browser review was intentionally non-exhaustive but found no blocking correctness problem; remaining Content-view behavior/UX improvements are deferred to the later whole-system polish pass.
- Post-closure invitation locale-return regression fixed at `0d98dfe`; GitHub Actions run `35749114796` is green at **366 tests / 1945 assertions**, PHPStan/Pint/Vite/ops/security green.
- Phase 7 — Submission / Response / Evaluation is **runtime technically complete / remote-CI green; final owner-local/browser/mobile/RTL acceptance pending** on `feat/phase-07-submission-evaluation`; binding contract: `docs/PHASE_07_SUBMISSION_EVALUATION.md`.
- Phase 7A — versioned InteractionDefinition kernel — complete on `ceeb85b63c92385a0b714b5d5e9116dfe9e94932`; GitHub Actions run `35756220221`: **372 tests / 1964 assertions**, PHPStan clean, changed-file Pint **272 files**, Vite/migrations/ops/backup/security green.
- Human/local Phase 7A acceptance recorded on 2026-09-22 from synchronized `bd4ca3a`: migration applied cleanly to the existing database; focused `InteractionDefinitionKernelTest` **6 tests / 19 assertions** passed; PHPStan **266/266** clean; dirty-only Pint passed; `git diff --check` clean; working tree clean; invitation → registration → Admission resume browser regression remained correct with intentionally unchanged visible behavior.
- Phase 7B — Submission / Response / evidence — technically complete on `7761a2f5a57fd06b8df4fa7e97397cd9f9a2a2ab`; GitHub Actions run `35766736605`: **379 tests / 2000 assertions**, PHPStan clean, changed-file Pint **284 files**, Vite/migrations/ops/backup/security green.
- Phase 7C — Evaluation — technically complete on `9e0338994f755f81c59ed6dfe9e5e96ad0afaa8f`; GitHub Actions run `35767588253`: **384 tests / 2025 assertions**, PHPStan clean, changed-file Pint **293 files**, Vite/migrations/ops/backup/security green.
- Phase 7D — productized interaction experience — technically complete on `d828dc43c9ef469a3532bfee9b6274d5dceb5f24`; GitHub Actions run `35771215549`: **390 tests / 2052 assertions**, PHPStan clean, changed-file Pint **305 files**, Vite/migrations/ops/backup/security green.
- Phase 7E — proof and closure — technically complete on `35236f7af4ab9168467383b867cd698f2f30755c`; GitHub Actions run `35772393444`: **393 tests / 2086 assertions**, PHPStan clean, changed-file Pint **307 files**, Vite/security/ops/backup green; migrations `200000`, `210000`, `220000` rollback and reapply successfully in CI.
- Owner local engineering validation is green on the synchronized Phase 7 branch. Manual browser feedback exposed reviewer discoverability friction: after entering/submitting work, the reviewer-side consequence was not obvious enough.
- Post-7E reviewer-attention correction is complete on `bd45c994535a2045fde7e23c924d103aaa666c25`: authorized Context reviewers now see a persistent `Review submissions (:count submitted)` entry on the Context Content page, with regression coverage proving the count changes after submit. GitHub Actions run `35824650618`: **394 tests / 2092 assertions**, PHPStan clean, changed-file Pint **307 files**, Vite/security/ops/backup green.
- Final browser/mobile/RTL acceptance remains open only to confirm the corrected reviewer discoverability and the existing interaction surfaces in real use.
- Cross-cutting Documentation-as-Content / system-audit runtime candidate is **technically green** at `6e6443051e93d4f0fd653758ba1979a7dc831de1`; GitHub Actions run `35848121420`: **398 tests / 2169 assertions**, PHPStan clean, changed-file Pint **327 files**, Vite/ops/backup/security green.
- That slice adds the authenticated `Reference` Context, official English IET System Manual as normal versioned Content, Guide/Documentation Blueprint, global contextual Help routing, precise Question/Correction/Idea feedback, immutable disposition → later sealed revision provenance, and explicit non-destructive manual source synchronization.
- While dogfooding the manual bootstrap, the full suite exposed hidden dependence on Eloquent create events for required UUID/hash/evidence fields. The authoritative Content/Blueprint revision Actions now supply those durable values explicitly, keeping materialization deterministic even when tests/tooling fake events.
- Owner-local acceptance is still required before formal Phase 7 closure: migrate + materialize the manual on the existing local database, run the focused/manual gates, inspect the System Manual/Help/feedback flows in browser (including a second non-manager user and RTL/mobile), then reconfirm the Phase 7 submit → reviewer-count → Evaluation flow.

This document describes repository implementation truth at the Phase 7 technical-completion candidate. Architecture remains governed by `docs/TARGET_ARCHITECTURE.md`, execution order by `docs/PRODUCTION_ROADMAP.md`, and Phase 7 acceptance by `docs/PHASE_07_SUBMISSION_EVALUATION.md` plus `Development-CodexReports/phase-07-submission-evaluation-report.md`.

## Established identity and platform foundation

Implemented:

- Laravel 13 application on PHP 8.4.
- conventional User authentication;
- invitation-oriented registration path;
- email verification;
- password reset;
- account active/suspended checks;
- locale/timezone handling;
- User → Actor separation;
- Actor identity link protection and archival behavior;
- platform access grants;
- platform roles:
  - Superadmin
  - GroupCreator
- platform capabilities:
  - CreateGroups
  - ManageUsers
  - ManageActors
  - ManagePlatformAccess
  - ViewPlatformAudit

Current Phase 4 identity/Profile state:

- Actor is still primarily a User-backed person; organization/system Actors and explicit multi-Actor acting authority remain later work.
- `ActorProfile` is the mutable presentation/profile layer attached to Actor rather than User.
- Profile is private by default and never treats account email as a public Profile field.
- Profile media stays in the private Asset pipeline and supports an always-visible image library plus one changeable displayed image.
- the displayed Profile image is reused as the participant avatar where authorization permits;
- reusable participant presentation uses avatar + human-facing identity and links to the Profile/reference surface;
- dashboard/header presentation uses displayed identity/avatar rather than treating username as the only human-facing identity;
- clicking another participant is side-effect free and never lazily creates or mutates that person's Profile;
- Concept-backed skills, interests and learning goals are implemented;
- skills may carry optional self-rated proficiency from 0–100%, stored through normalized Concept Assertion weight;
- Profile Need/Offer intent declarations are implemented with quantity, route, recurrence, temporal constraints, lifecycle, visibility and optional importance/urgency from 0–100%;
- purpose-specific Profile completeness/requirements and recipient-specific selective disclosure are implemented;
- Profile owner UX is read-first: existing data/cards stay visible while mutation forms/composers open on demand;
- Profile remains upstream state only: it does not create Planner Occurrences, Matches, Contracts, Commitments or Fulfillment.

## Context Kernel

Phase 5 introduces the first production Context abstraction.

Implemented:

- first-class `Context` UUID identity;
- explicit kinds:
  - Personal;
  - GroupSpace;
  - Admission;
  - Reference;
- explicit relational subtype bindings rather than polymorphic owner columns;
- one Personal Context per Actor;
- one GroupSpace Context per GroupSpace;
- one Admission Context per Admission;
- keyed managed Reference Contexts for shared maintained knowledge such as the official System Manual;
- idempotent provisioning Actions;
- deterministic existing GroupSpace backfill;
- Context authorization for viewing, creating/interacting with Content, Content management, Definition management and historical review;
- Personal Context restricted to its active verified Actor;
- GroupSpace Context delegating to existing GroupSpace authorization;
- Admission Context allowing candidate/reviewer collaboration before Membership without granting ordinary GroupSpace authority;
- Reference Context allowing active verified users to read/interact while its designated manager alone authors/manages the official material;
- terminal Admission Contexts preserve historical read access while denying mutation/interactions.

Context is a bounded collaboration/artifact environment. It is not Group Membership, Profile disclosure, Workflow, Planner, Match or Contract authority.

## Unified Content / Blueprint system

Phase 6 converges the previously mature Group Content engine and the simpler generic Context Content UI into one application experience.

Implemented:

- one Content substrate for Personal, GroupSpace, Admission and Reference Contexts;
- immutable Content revisions and sealed publication evidence remain authoritative;
- versioned `ContentBlueprint` + immutable `ContentBlueprintVersion`;
- built-in Blueprint catalog for diary/note, post, article, activity/report, evidence/work-sample, media album, book/booklet, lesson, workbook page, questionnaire shell and Guide/Documentation;
- Context-compatible Blueprint catalog/search;
- Blueprint clone provenance;
- no silent Blueprint upgrades;
- Context-local hidden Definition materialization bound to the exact Blueprint version;
- exact Blueprint-version provenance on both Definition and Content;
- initial Blocks/presentation/Concept defaults created directly on revision 1;
- interaction defaults snapshotted onto Content at creation rather than permanently delegated to Blueprint identity;
- Blueprint-first Quick → More details → full Studio authoring;
- raw Definition authoring retained only as an advanced/custom path;
- generic Context Studio with structured fields, Assets/media, Blocks, appearance, Outline, immutable history, publishing and archive/restore;
- legacy Group Content routes redirect to the same generic Context experience;
- generic Context Reader with reactions, rich annotations, attachments and Outline;
- immutable revision permalinks;
- immutable `ContentEvidenceReference` locators for exact revision/field/block/asset/relationship evidence;
- historical evidence resolves the exact sealed edition rather than the latest mutable Content.

Evidence/reputation boundary:

- Profile proficiency remains self-reported;
- Content can now be cited precisely as evidence;
- evidence references are not verification, endorsement, Contract acceptance or reputation by themselves;
- later verification/reputation logic must evaluate evidence through explicit rubrics/policies instead of hard-coding artifact counts into Content.

Questionnaire remains the human-facing authored artifact; the now-implemented Phase 7 InteractionDefinition/Submission/Response/Evaluation kernel owns structured respondent attempts and review evidence.

### System Manual / contextual Help

Implemented on the Phase 7 branch as a cross-cutting delivery capability:

- official English `IET System Manual` is normal Content rather than a parallel docs application;
- Book/Booklet root with independently revisioned Guide/Documentation child pages;
- current manual coverage includes system mental model, User/Actor identity, Groups/roles, Contexts, Content/Blueprints, Reader/annotations, publishing/evidence, Invitation/Admission, Submission/Evaluation, roadmap target, feedback workflow, Profile/Concepts/Need/Offer semantics and Group Agreements;
- every guide page separates current implemented behavior, efficient usage, authorization, ideal target behavior and common misunderstandings;
- `/manual` is linked from primary navigation;
- global Help/feedback maps the current route to the most relevant manual topic and deep-links to the usage section;
- active verified users can read and annotate the Reference Context without fake Group membership;
- only the Reference Context manager may author/manage official editions;
- user questions/corrections/ideas remain bound to the exact historical edition/section;
- maintainers can record immutable reviewed/accepted/rejected/superseded/incorporated dispositions;
- incorporated feedback is linked to the exact later sealed official revision;
- normal seed/bootstrap preserves authorized maintainer edits; explicit source sync is required to publish repository-source changes.

Translation boundary: English is currently the canonical manual content. Persian is next and must use a separately reviewable translation lifecycle; Arabic and Simplified Chinese follow. Localized navigation/feedback controls already exist, but generated translation must not be presented as native-reviewed documentation.


## Group governance kernel

Implemented:

- Groups;
- active Memberships with lifecycle events;
- contextual Spatie roles and permissions;
- built-in Owner and Member roles;
- custom Group roles;
- ownership-transfer integrity;
- invitation issuance, expiry, usage limits, targeted email and revocation;
- invitation acceptance evidence;
- Admission lifecycle;
- Agreement lifecycle and immutable acceptance evidence;
- Membership Agreement reacceptance;
- Group authorization policies.

Group permissions currently include:

- participate
- manage_group
- manage_members
- manage_roles
- manage_invitations
- approve_role_changes
- manage_admissions
- manage_agreements
- manage_spaces
- view_group_audit
- manage_simulations
- transfer_ownership

Current invitation/admission path:

~~~text
Invitation preview
→ register/login if needed
→ verify email
→ return to the same invitation
→ explicit Continue to admission
→ redeem invitation and create/reuse Admission
→ accept exact required Agreement versions
→ submit / review / clarification / resubmit
→ approve
→ finalize Membership
→ Group home
~~~

Phase 1 hardening now includes:

- registration creates User + Actor only and does not consume/redeem the invitation before verification;
- invitation-scoped login/verification returns to the invitation rather than a generic dashboard;
- known expired/revoked/exhausted invitations show safe explanatory states while unknown tokens remain non-disclosing;
- stale acceptance attempts revalidate current invitation state;
- exact Agreement-version evidence is checked on candidate submission and again at finalization;
- candidate/manager and cross-Group authorization isolation is explicitly tested;
- an Admission alone grants no normal Group access; finalized Membership does;
- practical invitation management includes one-time private-link copy, revocation, usage/status presentation, pagination, and exhausted status;
- invitation acceptance has a dedicated named rate limiter;
- new UI strings remain synchronized across supported locales.

Current limitations / deliberate future work:

- a pre-membership Admission Context now exists and supports candidate/reviewer Content collaboration;
- Admission does not yet host a configurable questionnaire, progressive Profile requirements or structured Submission/Response evidence requests;
- current clarification still uses Admission lifecycle/events/notes rather than a persistent candidate-reviewer Conversation;
- Phase 8 remains responsible for productizing Admission Context around Profile requirements, Submissions/evidence and shared/internal Conversations;
- realtime broadcasting and negotiated Contracts remain later roadmap work.

## Admission collaboration direction

The first Admission Context dependency now exists while preserving the proven `Invitation → Admission → Membership` boundary. Later Admission phases must build on this Context rather than granting pre-membership GroupSpace access.

Recorded direction:

- a pre-membership candidate collaborates through an `Admission` Context, not through ordinary Membership-gated GroupSpace access;
- the shared candidate/reviewer Conversation, optional reviewer-internal Conversation, and read-only system timeline have explicit audiences;
- messages, uploads, reactions, annotations, and human wording are collaborative records, not authoritative approvals or acceptances;
- structured requirements/evidence use the Submission/Response/Asset capabilities when Phase 7 exists;
- explicit domain actions remain authoritative for approval, exact Agreement-version acceptance, Contract activation, Membership finalization, and future Commitments;
- Group Agreement changes create new immutable versions with explicit activation/effective dates; candidate-specific negotiated terms use a separate negotiated Agreement/Contract rather than mutating Group-wide rules;
- real-time delivery later follows committed domain truth through outbox/queue/authorized broadcast and must reconstruct identically after reload.

See `docs/ADMISSION_COLLABORATION_ARCHITECTURE.md`.

## Group Spaces

Implemented:

- a default General Space on Group creation;
- Group and restricted access modes;
- explicit per-Actor allow/deny participation;
- Space participant/manager role;
- denial precedence;
- non-member restricted-Space access when explicitly allowed;
- stale suspended/removed Group membership cannot bypass authorization;
- Space management policies;
- archive rules and default-Space protection;
- chat;
- chat replies.

Current limitation:

- Space currently carries a single `kind` defaulting to chat even though Spaces now host multiple capabilities;
- the long-term model should move toward composable Space capabilities rather than one exclusive kind.

## Content kernel

The current Content subsystem is the strongest new platform layer.

Implemented:

### Content identity and lifecycle

- `SpaceContent` with stable public UUID;
- Context ownership with retained nullable GroupSpace compatibility provenance;
- Definition reference;
- Actor authorship;
- draft / published / archived lifecycle;
- active revision pointer;
- draft revision pointer;
- immutable identity/provenance;
- archive/restore lifecycle events.

### Content Definitions

- Context-scoped Content Definitions;
- immutable activated Definition versions;
- active/draft version pointers;
- safe field registry;
- current field types:
  - short_text
  - long_text
  - number
  - date
  - boolean
  - select
- structured payload validation and canonical hashing.

### Content revisions

- immutable revision records;
- exact Definition-version binding;
- canonical hashes;
- stable UUIDs;
- sealed publication evidence;
- versioned manifest format;
- explicit legacy-unsealed handling.

### Block documents

- fields or blocks composition modes;
- immutable revision-bound blocks;
- stable logical UUID across revisions;
- current block types:
  - paragraph
  - heading
  - quote
  - list
  - callout
  - divider
  - field
  - image
  - audio
  - video
  - file
- safe block styling.

### Assets

- reusable private Assets;
- MIME validation;
- SHA-256 identity;
- rights status;
- scan/processing status;
- publication readiness;
- publication evidence snapshots;
- authorized streaming/download;
- local/testing developer flow;
- production-oriented processing pipeline;
- content media placements;
- annotation attachments;
- optional Context provenance for Content Assets;
- Profile Assets remain outside Content Contexts;
- generic Context asset streaming/download authorization.

### Presentation

Built-in safe presentations:

- article
- lesson
- book
- minimal
- showcase

Safe presentation tokens include:

- background
- surface
- text
- muted
- accent
- border
- content width
- font scale
- radius
- heading style
- media style
- per-field safe styles

Context-scoped saved Render Templates and favorites exist; existing GroupSpace templates retain compatibility provenance.

### Outline / composition relationships

- revision-bound Content relationships;
- current relation type: contains;
- ordered children;
- cycle prevention;
- publication seals exact child revision and child manifest hash;
- Reader builds published nested Outline;
- Book → Lesson → Page use case demonstrated.

### Reader / Studio

Separate routes and concerns exist for:

- Reader;
- Studio/document editing;
- Document Layout;
- Appearance;
- Outline;
- asset access.

Published readers do not expose ordinary workflow controls.

Phase 5 also adds a focused generic Context Content surface:

- Personal “My Content” entry from the dashboard;
- Admission workspace entry from the Admission page;
- Context-local Definition creation/activation;
- draft creation, revision and publishing;
- Context Content list/read surface;
- generic Context asset access.

This first non-Group surface is intentionally simpler than the mature Group Reader/Studio. Productized reusable authoring belongs to Phase 6 Blueprints.

### Interactions

Implemented:

- reactions;
- annotations;
- private and Context-audience visibility;
- annotation kinds:
  - comment
  - note
  - question
  - answer
  - reply
  - correction
  - idea
- exact text-selection anchors with Unicode-safe offsets;
- field, block, asset, relationship and whole-revision anchors;
- composite/multi-target annotations;
- persistent authorized markers;
- focused contextual annotation UI;
- annotation attachments and voice/file flows;
- immutable feedback-disposition history for reviewed / accepted / rejected / superseded / incorporated states;
- incorporated feedback must reference a later sealed revision of the same Content, preserving proposal → official-revision provenance;
- Reference Content opens in clean-reading mode with annotation markers hidden by default while the discussion/overlay layer remains available.

Current limitations:

- annotations remain collaboration and are not a replacement for structured Submissions or authoritative domain Actions;
- feedback disposition does not yet provide a full maintainer triage queue, release-note linkage or official/support identity distinction;
- legacy `group_space_id` compatibility columns remain intentionally while Context migration proves stable;
- Outline currently exposes only contains;
- nested block storage exists but authoring remains mostly flat;
- search/taxonomy/discovery UX is incomplete;
- Content audience is inherited from its Context authorization model; finer productized audience semantics remain future work;
- archive library/recovery UX is incomplete;
- Reader/chat still need the Phase 9 real-time event/broadcast architecture.

## English workbook demonstration

An opt-in local/testing fixture exists:

- Group: British English File Study
- Space: Intermediate Plus
- Definition: Course Material
- saved appearance template: English Workbook · Magenta
- Content hierarchy:
  - Book
  - Lesson 1A
  - Page 6

It proves that one Content kernel can represent book/course, lesson, and independently revisioned page material with styling, Outline and annotations.

The default local/testing `DatabaseSeeder` now also materializes the official English IET System Manual as normal sealed Content in the shared authenticated Reference Context. Bootstrap is non-destructive to later maintainer edits; repository-source updates are applied intentionally with `php artisan system-manual:sync <owner-email>`, which creates/publishes newer Content revisions.

Known fixture debt:

- the workbook demo currently applies a built-in lesson presentation before applying the saved custom template. A repeat run may create unnecessary intermediate presentation revisions. This should be corrected when the fixture/Blueprint work is revisited.

## Agreement duplication

Current Group Agreements have their own versioned long-text content, lifecycle, hashes, approvals, activation and acceptance evidence.

This remains correct operationally, but the human-readable agreement body duplicates capabilities now present in the Content kernel.

Long-term target:

- Agreement remains the domain lifecycle/acceptance/effective-period object;
- an Agreement version may reference an exact sealed Content revision for its authored terms;
- acceptance evidence continues to bind exact immutable agreement/version/content evidence.

Do not prematurely collapse Agreement into Content.

## Legacy domains still present

The repository still contains:

- Story;
- StoryRole;
- Responsibility;
- construction-project demonstration seeders and associated older assumptions.

These represent an earlier architectural generation.

Target decision:

- do not extend Story;
- preserve any valuable data/ideas;
- migrate or remove the legacy model only through explicit later migration work;
- construction/project examples should eventually become Group/Domain Blueprints over the new kernels.

## Semantic / Concept system

Phase 3 Concept Kernel is implemented and closed.

Implemented foundations include:

- ConceptVocabulary and Concept identity;
- localized Concept labels;
- Concept Schemes and memberships;
- hierarchy edges and closure;
- typed Concept relations;
- generic Concept Assertions;
- explicit subject, predicate, visibility, provenance and temporal validity;
- immutable publication-evidence protection for sealed revision-bound assertions;
- Actor Profile reuse of the same Concept for different predicates such as skill, interest, learning goal, Need and Offer summaries.

The central invariant is preserved: the meaning is the Concept; the Actor's relationship to that meaning is the predicate/assertion. The system does not duplicate a Concept merely because one Actor is skilled in it while another needs or wants to learn it.

Profile skill proficiency reuses the existing assertion `weight` dimension as a normalized value rather than creating a second skill-specific semantic model.

See `docs/CONCEPT_KERNEL.md` and `docs/PHASE_03_CONCEPT_KERNEL.md`.

## Planning

Not implemented as a production domain.

Target primitives:

- Plan;
- ScheduleRule;
- Occurrence;
- Participant;
- Completion;
- Evidence.

## Need / Offer / exchange

The **Profile intent layer** is implemented; the full exchange/matching domain is not.

Current Phase 4 Profile declarations support:

- Need and Offer kind;
- canonical Concept reference;
- title/description;
- optional importance/urgency percentage (0–100);
- quantity/unit;
- location;
- origin → destination;
- optional round trip / return offset;
- one-time, ongoing, daily, weekly and monthly cadence;
- timezone/date/time-window constraints;
- inherited/private/authenticated/public item visibility;
- active / paused / closed lifecycle;
- coarse Actor Concept summaries for active Needs/Offers without taking over unrelated manual assertions.

These declarations describe **current participant intent**. They do not create matches, obligations or materialized schedules.

Future Phase 13 now comes first for the obligation path and remains responsible for:

- direct Proposal;
- Negotiation;
- Agreement/Contract;
- Commitment;
- Planner/Occurrence binding where commitments are scheduled;
- Fulfillment and review;
- financial-obligation handoff.

Future Phase 14 remains responsible for discovery:

- Match;
- ranking/eligibility logic;
- proposal handoff into the already-proven Phase 13 path.

A direct relationship never requires Need/Offer/Match, and no matching result itself creates an obligation.

## Accounting

No production ledger exists in the current branch.

Legacy migrations supplied outside the current branch show a prior attempt with:

- currencies;
- accounts;
- transactions;
- ledger entries;
- idempotency;
- cached balances.

The target redesign is documented in `docs/FINANCIAL_ARCHITECTURE.md`.

## Real-time infrastructure

Current collaborative UX is not yet built around a single authoritative domain-event/outbox/broadcasting architecture.

Target:

~~~text
transactional domain action
→ committed domain event / outbox
→ queued dispatch
→ authorized broadcast
→ client update
~~~

The database remains authoritative; broadcasting is transport only.

## Production operations

Current source validation is strong for the implemented scope, but production readiness is incomplete.

Required before broad production release includes:

- CI required on pull requests;
- deployment runbook;
- environment/secrets policy;
- database backup and tested restore;
- queue supervision;
- scheduled-job supervision;
- transactional email reliability;
- application/error monitoring;
- structured operational logs;
- rate limiting and abuse controls;
- security review;
- privacy/export/deletion policy;
- retention rules;
- moderation/reporting where public/community content exists;
- accessibility review;
- responsive/mobile acceptance;
- performance budgets, pagination and search;
- incident/recovery process;
- dependency/security update process.

These are continuous roadmap requirements, not a final afterthought.

## Deferred polish backlog

The owner accepted Phase 6 for roadmap progression while deliberately deferring non-blocking Content-view polish until the later whole-system review.

Track these items without pulling them into Phase 7 unless a concrete issue becomes a correctness, security, authorization, accessibility or data-integrity blocker:

- review Content library, creation, Studio and Reader behavior for interaction consistency and unnecessary friction;
- polish action/state feedback, navigation, disclosure behavior and view transitions where the current experience can be clearer;
- revisit Content-view responsive/mobile behavior and RTL presentation more exhaustively;
- fix small visual/behavioral defects discovered during later end-to-end use;
- perform a final cross-system UX/accessibility/cohesion pass after the roadmap capabilities are substantially complete;
- preserve the accepted Content/Context/Blueprint architecture while polishing views; do not replace proven domain boundaries merely for cosmetic consistency.

Specific defects should be added when observed rather than guessed or implemented speculatively now.

## Current highest-priority next milestone

Phases 1–6 are closed.

The active milestone is **Phase 7 — Submission / Response / Evaluation**.

Binding contract: `docs/PHASE_07_SUBMISSION_EVALUATION.md`.

Starting runtime baseline: `0d98dfe3c99e79dbdbc72dfc9b6f3fbe50a7f533`.

Current Phase 7 runtime candidate: `bd45c994535a2045fde7e23c924d103aaa666c25`.

Phase 7A is complete and locally accepted. Phase 7B is technically complete: one immutable-version-bound Submission attempt owns normalized draft Responses, explicit submit/withdraw Actions, reusable same-Context Asset evidence, authorized cross-Context immutable Content evidence, max-attempt enforcement, private pre-submit Admission drafts, and a canonical SHA-256 sealed submission manifest. Phase 7C is technically complete: authorized evaluator drafts, immutable version-bound rubric configuration, bounded score/criterion feedback, finalized Evaluation evidence hashes, draft privacy, finalized submitter visibility, and no hidden Admission/Membership transition. Phase 7D is technically complete: exact-revision Content Reader interaction cards, draft/resume/submit/withdraw UX, reusable Asset/evidence inputs, reviewer discovery/queue/detail, protected Submission-asset delivery, explicit Evaluation UX, four-locale copy, RTL-ready responsive layouts, and no Admission lifecycle side effects. Phase 7E is technically complete: opt-in school-exam and employment-application browser fixtures, historical version-drift proof, pre-Membership Admission isolation, full regression coverage, migration rollback/reapply proof and the Phase 7 implementation report are green. Local engineering validation is green, but the owner has **not** accepted the final browser UX because reviewer-side consequences were not obvious enough during manual use; Phase 7 remains at the human gate.

Phase 7 builds one versioned structured-interaction kernel for applications, exams, questionnaires, evidence responses and evaluations.

The owner-approved product direction is conversation-ready and now has a permanent connected-life north-star proof:

- a direct paid-work relationship must eventually connect Contract → Commitment → Planner/Occurrence → Fulfillment/evidence → financial obligation → payment/settlement/accounting;
- the system must always be able to explain scheduled/worked/accepted/earned/paid/outstanding/disputed state from linked authoritative records;
- direct Contract creation must not require Need/Offer/Matching; Matching is optional discovery and is therefore sequenced after the Contract/Commitment/Fulfillment kernel;
- structured interactions must be able to render later as cards inside Conversation;
- conversation/messages remain collaboration, never hidden authority;
- Phase 7 does not implement Admission v2 Conversation or realtime infrastructure;
- current Admission remains compatible until Phase 8 composes these capabilities into the conversation-first workspace.

Pre-implementation audit confirms reuse of:

- Context authorization, including Admission Context before Membership;
- private reusable Assets;
- immutable Content revisions/evidence;
- rich annotations for discussion rather than structured answers.

Phase 7 must not create a parallel form engine, a second Asset store, an Admission-only response system, or a generic Workflow/Conversation subsystem.

Binding downstream invariants:

- Actor is participant identity;
- Context is bounded collaboration/artifact environment;
- Group Membership is not universal Context authorization;
- Profile disclosure is not Context authorization;
- Admission Context access does not imply Group access;
- mutable Content drafts remain distinct from sealed publication evidence;
- Content Blueprints remain creation recipes, not permanent hidden authority/type systems;
- Submission/Response/Evaluation is authoritative structured interaction state;
- Conversation is collaboration, not authority;
- Admission v2 belongs to Phase 8;
- real-time transport belongs to Phase 9.

See `docs/PHASE_07_SUBMISSION_EVALUATION.md`, `docs/ADMISSION_COLLABORATION_ARCHITECTURE.md`, `docs/PRODUCTION_ROADMAP.md` and `docs/TARGET_ARCHITECTURE.md`.
