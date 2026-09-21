# IET Current State

## Snapshot

Validated Phase 1 closure state:

- Branch: `feat/group-spaces-communication`
- Original validated code baseline: `f57ee430f96afcdb1ecc32f5b644fcb057dae6f4`.
- Canonical Phase 0 / Phase 1 starting HEAD: `dee86677cdfd95b6b2887843cba699b21b07f6f0`.
- Phase 1 closure validation reported by the human owner on 2026-09-21:
  - focused onboarding/hardening gate: 41 tests passed, 216 assertions;
  - full PHPUnit suite: 282 passed, 1437 assertions;
  - PHPStan: no errors;
  - Pint: passed;
  - Vite production build: passed;
  - `git diff --check`: clean.
- Human owner reports the implemented browser onboarding/hardening flows behave as intended.
- Phase 1 introduced no migrations and no new third-party service.

This document describes the implementation intended to be committed as the Phase 1 closure state. Target/future architecture remains separate in `docs/TARGET_ARCHITECTURE.md`.

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

Current limitation:

- Actor is still effectively designed primarily around one User-backed person.
- organization/system Actors and explicit User→Actor acting authority do not yet exist.
- Phase 4A now provides the professional ActorProfile identity/media foundation, but structured Profile facts, Concept-backed skills/interests, selective sharing, and completeness/requirements remain 4B/4C work.

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

- Admission does not yet host a configurable questionnaire, progressive Profile requirements, structured document/evidence requests, or a pre-membership Admission Context;
- current clarification uses Admission lifecycle/events/notes rather than a persistent candidate-reviewer Conversation;
- direct invitation email delivery remains a documented manual private-link product choice for Phase 1; operational transactional-email configuration belongs to Phase 2;
- context-scoped Admission collaboration, structured submissions/evidence, live broadcasting, and negotiated Contracts belong to later roadmap phases and must not be retrofitted into Phase 1 ad hoc.

## Admission collaboration direction

The next Admission architecture must preserve the proven `Invitation → Admission → Membership` boundary while replacing note-heavy clarification UX with context-scoped collaboration when its dependencies exist.

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
- GroupSpace ownership;
- Definition reference;
- Actor authorship;
- draft / published / archived lifecycle;
- active revision pointer;
- draft revision pointer;
- immutable identity/provenance;
- archive/restore lifecycle events.

### Content Definitions

- Space-scoped Content Definitions;
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
- annotation attachments.

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

Space-scoped saved Render Templates and favorites exist.

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
- Content is still hard-bound to GroupSpace;
- Definitions and saved Render Templates are Space-local;
- Outline currently exposes only contains;
- nested block storage exists but authoring remains mostly flat;
- search, taxonomy and semantic classification are not implemented;
- content audience is primarily inherited from Space;
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

Not implemented in the current application.

Legacy migrations supplied outside the current branch demonstrate useful prior ideas:

- Concept identity;
- Concept closure;
- typed Concept relations;
- generic Concept attachments;
- User-specific Concept usage.

The target redesign is documented in `docs/CONCEPT_KERNEL.md`.

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

Not implemented.

Target domain will separate:

- Need;
- Offer;
- Match;
- Proposal;
- Negotiation;
- Agreement;
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

Phase 1 — Production Invitation + Registration + Admission Journey — has completed implementation and validation. Its durable evidence is in `docs/PHASE_01_INVITATION_ONBOARDING.md` and `Development-CodexReports/phase-01-invitation-onboarding-report.md`.

Phase 2 — Delivery and Operations Baseline — is complete at the provider-neutral development baseline.

Validated Phase 2 capabilities include CI, committed Composer/npm lockfiles, strict `npm ci`, dependency audits, deploy/version and request correlation, database queue operation, scheduler execution, failed-job visibility, SQLite backup→restore smoke, private-storage guidance, abuse-control inventory, and an executable operations runbook.

Owner-local validation after synchronizing the Phase 2 branch confirmed:

- Composer metadata/install from lock: passed;
- npm install/audit/build from lock: passed;
- PHPUnit: 284 tests / 1441 assertions;
- PHPStan: no errors;
- Pint on all Phase 2 PHP changes: passed;
- migrations: all ran;
- scheduler list and execution: passed;
- database queue worker: started and drained cleanly;
- failed jobs: none;
- Laravel `/up` liveness page: healthy in the browser.

Production-host-specific proof is deliberately not a blocker for Phase 3. Before production release, the selected deployment target must still exercise real worker/scheduler supervision, enabled transactional mail, operational monitoring/log retention, automated backups, an isolated restore drill, and private media storage. Those remain governed by `docs/OPERATIONS_RUNBOOK.md` and later production-hardening/release gates.

Phase 3 — Concept Kernel — is complete.

Owner-local closure validation on the final Phase 3 branch confirmed:

- focused Concept + publication suite: 16 passed / 118 assertions;
- full PHPUnit suite: 292 passed / 1493 assertions;
- PHPStan: no errors;
- Pint across Phase 3 PHP: 55 files passed;
- Vite production build: passed;
- migrations: current;
- working tree: clean.

The active implementation milestone is:

> Phase 4 — Actor/Party and progressive Profile

Branch: `feat/phase-04-actor-profile`.
Contract: `docs/PHASE_04_ACTOR_PROFILE.md`.

Current Phase 4 state:

- 4A professional identity + profile media: owner-local accepted at 298 tests / 1528 assertions, PHPStan/Pint/build green and clean working tree;
- 4B semantic Profile + recurring Need/Offer declarations: active implementation;
- 4C selective sharing/completeness/final closure: pending.

4B preserves future boundaries: recurring Profile declarations describe current cadence/constraints without generating Planner Occurrences, performing Need/Offer matching, or creating obligations.

Phase 4 keeps authentication User data separate from Actor/Profile data, defaults Profile visibility to private, and reuses the private Asset/media pipeline for profile images.
