# IET Current State

## Snapshot

### Selective assembly release reconciliation

The release line is now being reconciled through `codex/ideal-v1-selective-assembly` rather than by wholesale merging later aggregate candidate branches.

The assembly was created exactly at accepted integration SHA `891b333c49f166e61b9fa466e30742b3d70996c0`. Later planner/temporal/calendar/publication branches are treated as implementation/evidence sources whose changes must be admitted independently.

**S0 — baseline certification is remotely closed.** PR #30 merged the reviewed S0 changes into `codex/ideal-v1-selective-assembly` at `2b89e301ef7f167083445cd305847f83dbf3e048`; post-merge CI `36236888120` passed.

S0 strengthened CI so assembly/S0/release pushes run repository-wide Pint and explicitly compile Blade views. That stronger gate exposed 15 pre-existing formatter issues across 923 PHP files; they were normalized by Pint in formatter-only commit `e57b29d2de68f97568187a19de60c9bbabf103ca`. Final S0 proof is **923 Pint files passed, PHPStan 0 errors, Blade compile passed, MySQL/SQLite/ops passed, 552 tests / 3511 assertions, Vite passed, npm audit 0 vulnerabilities, Composer audit clean**.

The selective assembly process is now governed by `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`. Existing modules are reviewed and selectively improved one at a time; candidate branches remain source libraries rather than wholesale merge targets.

The owner has changed the gate from end-deferred browser acceptance to **module-by-module browser acceptance before final assembly**. Therefore the current gate is **M0/S0 baseline browser smoke**. M1 — access, identity, registration provisioning, wallet/default monetary unit — does not begin until that baseline browser check is explicitly accepted.

A governance gap is also recorded: the current GitHub ruleset protects `main`, not the selective assembly branch. PR-only/no-force-push discipline is therefore procedural until repository settings are extended.

### Publishable v1 hardening

Phase 22 and the publishable-v1 hardening line are integrated on `integration/ideal-v1`. The first publication remains intentionally bounded: deterministic human workflows and durable domain evidence are the product; AI Copilot, Generic Workflow extraction, Reputation and Recommendations are post-v1 work. See `docs/PUBLISHABLE_V1_RELEASE_GATE.md`.

Native-Persian UI localization hardening is integrated. PR #25 merged at `edff668b6e8ad6c4a58a7d6c08bb54a821ce9662`; independent post-merge CI run `36160798652` passed. A subsequent multilingual completion pass extends the same coverage contract to Arabic and Simplified Chinese: all supported UI locales now mirror the English locale-module set, Access/Intent no longer fall back to English, the 11 later workflow modules are localized, recurring untranslated implementation jargon is removed from audited surfaces, and CI validates keys plus runtime placeholders.

The multilingual completion line was later integrated at `b3df2175006a09471d3e72abf14cd8563cb759d7` and frozen as immutable `release/ideal-v1-rc-6`, with **546 tests / 3492 assertions** and the full MySQL 8.4, SQLite/ops, Pint, PHPStan, Vite, npm-audit and Composer-audit gates green.

Owner browser acceptance then exposed a separate presentation-hardening class that automated locale parity had not yet covered: a raw missing translation key (`ui.actions.cancel`) and an escaped Blade expression rendered literally in the account menu. Publish hardening now covers those failure modes directly, improves the account menu presentation, removes additional audited hardcoded-English controls from Platform Access / Groups / Commitment planning / Content Studio accessibility, and adds source + rendered regressions for missing UI keys, escaped server-side Blade expressions, literal English primary controls and hardcoded English ARIA labels. RC-6 remains immutable as the pre-correction checkpoint; browser acceptance must continue from the next green integrated RC.


### Continuous Ideal-v1 integration

Active integration branch:

`integration/ideal-v1`

Root baseline:

`2c7a5c35a31fe86d761a1cafd189560bec220784`

Historical Phase 1–22 integration used continuous remote development with browser acceptance deferred to `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`. **That process is no longer the active selective-assembly gate**; current modules follow `docs/SELECTIVE_ASSEMBLY_ROADMAP.md` and require owner browser acceptance before merge/progression.

The active line intentionally excludes the experimental/unused AI-assistance and Development-Origin runtime. AI returns only at the later dedicated roadmap phase.

Foundation F0 reconstructs the useful office alpha on the clean baseline:

~~~text
Access Invitation
→ inspect welcome
→ register
→ verify email
→ Get Started
→ guided Need / Offer
→ permission-aware read-only Intent Directory
~~~

The office-alpha Intent model reuses `ActorProfileIntent`; cash/mixed-value fields are negotiation preferences only and create no Match, Contract, ownership, debt, payment or accounting truth.

Content/Context direction is now explicit: one independent Content system, home Context for authoring/authorization, cross-context presentation/reference, exact immutable revision targets for evidence, and no Group-specific duplicate Content stores.

Foundation F0 runtime checkpoint is now remotely green at `f5b55fb3f1d426e995549efc90856cfd9ac60b34` on GitHub Actions run `35924838454`: **411 tests / 2257 assertions**, changed-file Pint **366 files**, PHPStan clean, Vite build green, migration rollback/reapply + scheduler/database-queue smoke green, SQLite backup/restore green, npm audit 0 vulnerabilities and Composer security audit clean.

Remote milestones continue automatically after green CI; exact SHA/CI/report/worksheet evidence is recorded at each integration checkpoint.

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

### Published Content Library and cross-Context placement

Phase 9 adds a viewer-authorized published Content Library and explicit cross-Context presentation without moving or copying the source artifact.

Implemented:

- only sealed, published Content the current viewer is authorized to read appears in the Library;
- Library filtering by search, Blueprint/purpose and semantic Concept;
- Content retains one immutable home Context for authoring and source authorization;
- `ContentPlacement` presents that same Content identity in another Context;
- placement creation requires the acting User to be able to read the source/home Context **and** manage Content in the target Context;
- placement grants the target Context audience read access to the published artifact only; it does not grant source Context visibility, Studio/edit/revision authority or transitive resharing;
- normal placement follows the Content item's current published revision;
- exact `ContentEvidenceReference` identity remains pinned to its sealed historical revision/target even after later publication;
- removing a placement revokes target-only access; presenting it again reactivates the same placement identity instead of creating duplicate provenance.

Current limitations:

- placement is direct authorized presentation, not yet a cross-party request/approval workflow;
- Library filtering does not yet expose every roadmap refinement such as author/language/date;
- placement access is intentionally read-oriented; interaction still follows the Content home Context rules;
- richer card action menus and broad UX polish remain in the later cross-system polish pass.

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

Translation boundary: the application UI now has complete structural coverage for English, Persian, Arabic and Simplified Chinese. Persian has received a native-style hardening pass. Arabic and Simplified Chinese now have real localized copy for every English UI locale module and no English passthrough files; they remain candidates for later native-speaker editorial polishing rather than being falsely labeled native-reviewed. CI enforces locale-file/key coverage, placeholder parity and the absence of English passthroughs. English remains the canonical **System Manual content** for this release; document/manual translation is a separate versioned-content lifecycle.


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
- Phase 11 now provides a persistent shared candidate/reviewer Conversation in mutable Admission Contexts; Admission lifecycle/events remain the only authoritative state transitions;
- later Admission polish may add configurable Profile requirements, richer structured evidence cards and reviewer-internal Conversation audiences without changing the shared Context Conversation kernel;
- realtime broadcasting and negotiated Contracts remain later roadmap work.

## Admission collaboration direction

The first Admission Context dependency now exists while preserving the proven `Invitation → Admission → Membership` boundary. Later Admission phases must build on this Context rather than granting pre-membership GroupSpace access.

Recorded direction:

- a pre-membership candidate collaborates through an `Admission` Context, not through ordinary Membership-gated GroupSpace access;
- the shared candidate/reviewer Conversation and read-only source-linked Timeline now exist; an optional reviewer-internal Conversation remains future audience work;
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
- Context Conversation now provides the durable collaboration-message substrate; realtime event/outbox/broadcast transport remains later work and must never replace database source truth.

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

The timezone-aware Planner is implemented.

Current primitives include:

- Plan;
- PlanParticipant;
- PlanScheduleRule;
- PlanOccurrence;
- PlanReminder;
- durable Plan/Occurrence lifecycle events;
- Context-scoped authorization;
- personal activities;
- Commitment → Plan binding;
- Today/list/calendar views.

Planner records remain authoritative for schedule truth. Home/Today only derives from them.

## Need / Offer / exchange

The Profile Intent and explainable matching layers are implemented.

Current behavior includes:

- Need / Offer direction;
- canonical Concept;
- subject / arrangement semantics;
- quantity/unit;
- location/origin/destination;
- timezone/date/time/recurrence constraints;
- value/currency/cash-range preferences;
- item visibility and lifecycle;
- owner-driven deterministic matching;
- normal Intent policy before candidate disclosure;
- human-readable alignment reasons;
- exact originating + matched Intent provenance when a Relationship is explicitly created.

Matching is discovery only. It creates no agreement, Proposal, Contract, obligation or Accounting truth.

See `docs/PHASE_19_NEED_OFFER_MATCHING.md`.

## Accounting

Personal Accounting and the separate Financial Obligation/Settlement bridge are implemented.

Personal Accounting provides:

- MonetaryUnit;
- Ledger;
- balanced immutable JournalEntry / JournalLine truth;
- income, expense, transfer, opening balance, reversal/correction;
- per-ledger and per-period summaries;
- Context authorization.

Economic obligation truth provides:

- accepted Fulfillment;
- FinancialObligation;
- pending/confirmed/rejected Settlement;
- confirmed paid/outstanding derivation;
- explicit optional posting into Personal Accounting.

The two domains remain intentionally distinct: an obligation/payment claim is not a personal ledger entry until explicitly posted.

See `docs/FINANCIAL_ARCHITECTURE.md`, Phase 12 accounting docs and Phase 17 financial-obligation docs.

## Real-time infrastructure

Phase 22 implements the first authoritative notification/realtime transport seam:

~~~text
transactional domain action
→ transactional recipient notification outbox
→ post-commit queued delivery
→ durable database notification/read state
→ authorized private broadcast
→ client refresh
~~~

Implemented:

- idempotent notification outbox;
- database-backed user inbox and read state;
- Planner reminder emission;
- lifecycle projection for the principal coordination/review/financial flows;
- private per-user broadcast authorization;
- Laravel Reverb server configuration;
- Echo/Pusher browser subscription;
- live inbox/navigation unread refresh.

The database and existing domain kernels remain authoritative. Broadcasting is transport only and may be disabled without losing durable notification state.

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

**Publishable Ideal-v1 hardening** is the active release checkpoint on `feat/ideal-v1-publishable-hardening`.

Phase 22 is already integrated into `integration/ideal-v1` at `25a24e0fd0853347d73ec7b262ac274f76f40c6a`. The immediate objective is not Phase 23 expansion; it is to close inherited release defects, synchronize the canonical documentation, obtain a completely green exact candidate, integrate that candidate, and freeze one immutable release-candidate SHA for owner acceptance.

Closed hardening scope currently includes:

- reserved-email Access Invitations are constrained to one use, with registration availability re-checked under a row lock;
- Intent Directory authorization happens in SQL before pagination;
- Directory filters use real pagination and preserve useful query state;
- create → Directory highlighting is consumed coherently;
- Profile Intent editing exposes the guided subject/arrangement/value facets;
- regression coverage includes the >200 hidden-record authorization defect and consumed reserved-invitation reuse;
- the publication boundary and first-month learning posture are defined in `docs/PUBLISHABLE_V1_RELEASE_GATE.md`.

Release sequence:

~~~text
hardening branch completely green
→ final eagle-view release review
→ canonical docs synchronized
→ PR / integration into integration/ideal-v1
→ integration CI completely green
→ freeze immutable release-candidate SHA
→ owner local setup + cumulative 0→100 browser acceptance
→ regression-tested fixes for any browser findings
→ first stable publishable release
~~~

AI Copilot, Generic Workflow extraction, Reputation and Recommendations remain post-v1 work unless a concrete defect proves that one is necessary for an already-supported v1 journey. Do not resume speculative roadmap expansion before the publishable candidate and browser acceptance are closed.

