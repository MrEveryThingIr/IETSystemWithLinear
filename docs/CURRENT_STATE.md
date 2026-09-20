# IET Current State

## Snapshot

Verified architecture baseline:

- Branch: `feat/group-spaces-communication`
- Baseline commit before this documentation update: `f57ee430f96afcdb1ecc32f5b644fcb057dae6f4`
- Local validation reported by the human owner:
  - PHPUnit: 273 passed, 1386 assertions
  - PHPStan: no errors
  - Pint: passed
  - Vite production build: passed
  - working tree: clean

This document describes the implementation that exists at that baseline. It is deliberately separate from the target architecture.

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
- there is no progressive Profile domain yet.

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
→ redeem invitation
→ Admission
→ accept required Agreement versions
→ submit/review/approve
→ finalize Membership
~~~

This backend foundation is useful and should be preserved.

Current limitation:

- the journey is technically functional but not yet a polished production onboarding experience;
- Admission does not yet host a configurable onboarding questionnaire/profile requirements;
- a pre-membership Admission Context does not yet exist;
- invitation email delivery/notification and full operational UX still require production hardening.

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

The next implementation milestone is not Concepts, Planner, Finance, or Group Blueprints.

It is:

> Production Invitation + Registration + Admission Journey

The goal is that a completely new person can receive one invitation link and complete the allowed onboarding path without developer assistance.

The exact scope and gate are in `docs/PHASE_01_INVITATION_ONBOARDING.md`.
