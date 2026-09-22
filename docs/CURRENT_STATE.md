# IET Current State

## Snapshot

Current implementation baseline:

- Branch: `feat/phase-05-content-context`.
- Phase 1 — Invitation/registration/admission journey: complete.
- Phase 2 — Delivery and operations baseline: complete at the provider-neutral baseline.
- Phase 3 — Concept Kernel: complete.
- Phase 4 — Actor/Party + progressive Profile: complete and human-owner accepted.
- Phase 5 — Generic Content Context: **complete and human-owner accepted on 2026-09-22**.
- Frozen Phase 5 runtime candidate: `34bd6b8957e4ecc2b0474bc0b7d163ae010dc749`.
- GitHub Actions run `35725999556` on that exact runtime commit:
  - PHPUnit: **344 passed / 1807 assertions**;
  - PHPStan: no errors;
  - Pint: **216 files passed**;
  - Vite production build: passed;
  - Context migrations / scheduler / database-queue smoke: passed;
  - SQLite backup → restore smoke: passed;
  - npm audit: 0 vulnerabilities;
  - Composer security audit: clean.
- Phase 6 — Content Blueprints and unified productized authoring is the next active roadmap phase.

This document describes repository implementation truth at the final Phase 5 human acceptance gate. Future architecture remains governed by `docs/TARGET_ARCHITECTURE.md` and execution order by `docs/PRODUCTION_ROADMAP.md`.

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
- explicit relational subtype bindings rather than polymorphic owner columns;
- one Personal Context per Actor;
- one GroupSpace Context per GroupSpace;
- one Admission Context per Admission;
- idempotent provisioning Actions;
- deterministic existing GroupSpace backfill;
- Context authorization for viewing, creating/interacting with Content, Content management, Definition management and historical review;
- Personal Context restricted to its active verified Actor;
- GroupSpace Context delegating to existing GroupSpace authorization;
- Admission Context allowing candidate/reviewer collaboration before Membership without granting ordinary GroupSpace authority;
- terminal Admission Contexts preserve historical read access while denying mutation/interactions.

Context is a bounded collaboration/artifact environment. It is not Group Membership, Profile disclosure, Workflow, Planner, Match or Contract authority.

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
- private and Space visibility;
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
- annotation attachments and voice/file flows.

Current limitations:

- annotations are not a replacement for structured Submissions;
- no application/exam/questionnaire response engine exists yet;
- legacy `group_space_id` compatibility columns remain intentionally while Context migration proves stable;
- the generic Personal/Admission authoring surface does not yet expose the full Group Reader/Studio builder experience;
- Outline currently exposes only contains;
- nested block storage exists but authoring remains mostly flat;
- search, taxonomy and semantic classification are not implemented;
- Content audience is inherited from its Context authorization model; finer productized audience semantics remain future work;
- archive library/recovery UX is incomplete;
- Reader/chat still need a coherent real-time event/broadcast architecture;
- reusable Content Blueprints do not exist.

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

The default `DatabaseSeeder` remains minimal and currently bootstraps only the local test User/Actor and active Superadmin access.

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

Future Phase 13 remains responsible for:

- Match;
- ranking/eligibility logic;
- proposal handoff.

Future Phase 14 remains responsible for:

- Proposal;
- Negotiation;
- Agreement/Contract;
- Commitment;
- Fulfillment.

No matching result itself creates an obligation.

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

## Current highest-priority next milestone

Phases 1–4 are closed.

Phase 5 is closed. The next active implementation milestone is **Phase 6 — Content Blueprints and unified productized authoring**.

Frozen runtime candidate:

`34bd6b8957e4ecc2b0474bc0b7d163ae010dc749`

The human owner applied both Context migrations on the existing database and reported **25 focused tests / 156 assertions**, **344 full tests / 1807 assertions**, PHPStan clean, Pint clean, Vite green, `git diff --check` clean and a clean tree. Browser validation confirmed Personal Context Content creation works.

Phase 6 must now productize the same advanced Content kernel across every Context instead of maintaining a second simplified authoring experience.

Binding downstream invariants:

- Actor is participant identity;
- Context is bounded collaboration/artifact environment;
- Group Membership is not universal Context authorization;
- Profile disclosure is not Context authorization;
- Admission Context access does not imply Group access;
- mutable Content drafts remain distinct from sealed publication evidence;
- Content Blueprint productization belongs to Phase 6;
- Submission/Response/Evaluation belongs to Phase 7;
- full Admission v2 requirements/Conversation belongs to Phase 8.

See `docs/PHASE_05_GENERIC_CONTENT_CONTEXT.md`, `docs/PRODUCTION_ROADMAP.md` and `docs/TARGET_ARCHITECTURE.md`.
