# IET / EveryThing Developer System Handbook

## Purpose and authority

This is the self-contained orientation and change guide for a human developer or an AI agent working on IET / EveryThing. It explains what the system is, what currently exists, which subsystem owns each fact, where code belongs, how modules relate, and how a change must be reviewed and validated.

This handbook is a map, not permission to override the canonical architecture. When repository access is available, read the following in order before making a material change:

1. `AGENTS.md`;
2. `.ai/rules/index.md` and every rule matching the files in scope;
3. `docs/PROJECT_COMPASS.md`;
4. `docs/CURRENT_STATE.md`;
5. `docs/TARGET_ARCHITECTURE.md`;
6. `docs/CAPABILITY_MESH_ARCHITECTURE.md`;
7. `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`, which controls active recovery/review;
8. `docs/PRODUCTION_ROADMAP.md`;
9. `docs/CONTINUOUS_REMOTE_EXECUTION.md`;
10. `docs/EXAMPLE_STORY_WORLD.md`;
11. the active milestone contract and report;
12. relevant ADRs, especially `docs/ADR-001-identity-authority-and-simulation-boundaries.md`.

If this handbook conflicts with those sources, the canonical sources and accepted ADRs win. A chat, issue, branch name, old migration, or generated proposal is not architecture authority.

## Snapshot represented by this handbook

This handbook was prepared on 2026-09-26 on the selective-assembly branch. The accepted runtime foundation was originally created from:

~~~text
branch: codex/ideal-v1-selective-assembly
runtime foundation: 891b333c49f166e61b9fa466e30742b3d70996c0
M00 governance merge: 24cddb2b5eaa199e90df88ccf779760c9cd24590
handbook addition: cf6023330fdade51b1847b6743be04ea39c70724
~~~

The runtime foundation is the accepted integration baseline after Phase 1–22 and publish-UI localization/rendering hardening. Subsequent S0/M00 work strengthened CI and recorded browser-gated assembly governance. Owner browser inspection confirmed that the foundation is technically healthy but predates several already-tested later improvements. M00 is therefore a valid technical baseline, not the final intended product checkpoint. The active next work is F1 Shared Temporal Fabric recovery, not M01 registration.

M00 evidence is recorded in `Development-CodexReports/M00-certified-foundation-browser-baseline-report.md`. The certified process checkpoint reports 552 tests / 3511 assertions, repository-wide Pint across 923 files, PHPStan, Blade compilation, MySQL/SQLite/operations, Vite, npm audit and Composer audit green. These are remote gates, not browser proof.

Relevant preserved development lines are sources for selective review, not automatically accepted code:

| Line | SHA | Meaning |
| --- | --- | --- |
| `release/ideal-v1-rc-6` | `b3df2175006a09471d3e72abf14cd8563cb759d7` | Immutable multilingual pre-browser-correction checkpoint. |
| `integration/ideal-v1` | `891b333c49f166e61b9fa466e30742b3d70996c0` | Accepted integration baseline used here. |
| `integration/ideal-v1-planner-temporal-candidate` | `4e553fe30391e336b4d202d74aae9742f5fd7528` | Planner execution-window and evidence candidate. |
| `integration/ideal-v1-temporal-calendar-reconcile` | `536392af117a60a28bd068d70a91d792a0f1999e` | Planner/calendar reconciliation plus a documentation-only configurable-form proposal. |
| `codex/release-first-publication-hardening` | `5830cb44e09c0238f1f1591f0134419ba67834c3` | Divergent source of user provisioning, temporal UI, ambient header, calendar and related work. Do not merge wholesale. |
| `codex/ideal-v1-selective-assembly` | M00 governance merged at `24cddb2b5eaa199e90df88ccf779760c9cd24590`; later documentation commits build on it | Active module-by-module assembly. Fetch and inspect its current remote SHA before starting work. |

There is no accepted stable `v1.0.0` tag at this snapshot. Historical release-candidate refs must not be rewritten. New defects require new commits, complete validation, and a newly numbered candidate.

## Product in one sentence

IET is a modular Laravel coordination platform that connects identity, governed collaboration, semantic meaning, versioned Content, structured interaction, planning, exchange, agreements, fulfillment, financial consequences, accounting, and durable history without collapsing those concerns into one unsafe universal object.

The design principle is:

> Generic inside, specific outside.

Internally the application reuses kernels such as Context, Content, Concept, Submission, Plan, Contract, Commitment, Fulfillment and Ledger. Externally users should see understandable actions such as “publish an article”, “invite a member”, “schedule work”, “submit evidence”, “accept terms”, or “record an expense”.

## Product lifecycle and composition

The conceptual lifecycle is:

~~~text
Identity
→ Representation
→ Context
→ Participation
→ Meaning
→ Content and Communication
→ Interaction
→ Planning
→ Intent / Opportunity
→ Proposal / Negotiation
→ Agreement / Commitment
→ Fulfillment / Evidence
→ Obligation / Settlement
→ Accounting
→ Learning / Reputation / Discovery
→ Reuse through Blueprints
~~~

Not every journey uses every step. A personal diary may need only Actor, Personal Context and Content. A simple sale may use Intent, Relationship, discussion and an optional payment record. Paid work may compose Relationship, Contract, Commitment, Planner, Fulfillment, Financial Obligation, Settlement and Accounting.

Each later step must be explicit. A Need is not a Match. A Match is not a Proposal. Conversation is not acceptance. A Contract is not Fulfillment. Fulfillment is not payment. A Settlement claim is not automatically a personal ledger entry.

## Non-negotiable system invariants

1. The application remains a modular Laravel monolith until operational evidence requires otherwise.
2. `User` is the authentication principal; `Actor` is the domain participant.
3. Platform authority, Group participation, Context access and Profile disclosure are separate authorization systems.
4. Group Membership never grants global or cross-Group authority.
5. Context access never silently grants Membership, Contract, Planner, financial or other domain authority.
6. Conversation and Content may explain or evidence an action but never substitute for an authoritative transition.
7. Critical multi-record transitions belong in explicit transactional Actions.
8. Policies and mutation Actions recheck authorization. Possessing an ID or URL grants nothing.
9. Published Content revisions, accepted agreement/contract versions, posted accounting entries and comparable historical evidence are immutable.
10. Corrections create revisions, superseding versions, reversals or explicit correction events; history is not edited away.
11. Realtime delivery is transport only. Reloading from the database must reconstruct authoritative state.
12. Planner owns schedule and occurrence execution truth; Home/Today and calendars are projections.
13. Matching is advisory and creates no obligation.
14. Financial Obligations and Settlements are economic-domain truth; Journal entries are accounting truth. Crossing that boundary requires an explicit posting Action.
15. Balances and summaries are derived, never authoritative counters.
16. Shared migrations are append-only and must not assume `migrate:fresh`.
17. Dynamic configuration uses bounded registries and validated data. Database content must never execute arbitrary PHP, Blade, JavaScript, SQL or CSS.
18. A generic abstraction is accepted only after at least two unrelated use cases prove it.
19. AI may later prepare drafts for existing Actions but may never bypass policy, validation or explicit human-authorized transitions.
20. Simulation or experiment history cannot become live authority, evidence, reputation, obligation, payment or accounting truth.

## Technology and repository shape

The accepted baseline uses:

- PHP `^8.3`; local development currently uses PHP 8.4;
- Laravel 13;
- Livewire 4;
- Blade;
- Tailwind CSS 4;
- Vite 8;
- PHPUnit;
- PHPStan through Larastan;
- Laravel Pint;
- MySQL 8+/InnoDB as the production concurrency target;
- SQLite for fast tests and selected operational checks;
- Laravel queues, scheduler, database notifications, Reverb and Echo.

Important directories:

| Path | Responsibility |
| --- | --- |
| `app/Models` | Persistent domain records and relationships. Models do not replace Actions for critical transitions. |
| `app/Actions` | Transactional mutations, lifecycle transitions, provisioning and cross-record invariants. |
| `app/Policies` | Server-authoritative authorization decisions. |
| `app/Livewire` | Interactive application pages and form orchestration. |
| `app/Http/Controllers` | Link entry points, downloads/streams, redirects and focused HTTP endpoints. |
| `app/Support` | Pure/domain support services, projections, registries, normalizers and formatters. |
| `resources/views` | Blade and Livewire presentation. |
| `resources/js` | Progressive browser behavior and realtime client integration. |
| `routes/web.php` | Browser route map and middleware/policy boundaries. |
| `routes/console.php` | Scheduled domain maintenance and queue/outbox commands. |
| `routes/channels.php` | Private broadcast authorization. |
| `database/migrations` | Append-only schema evolution. |
| `database/seeders` | Minimal local/test bootstrap and explicit opt-in examples. |
| `lang/{en,fa,ar,zh_CN}` | Supported UI message catalogs. English is the structural source. |
| `tests/Feature` | Most behavioral, authorization and integration coverage. |
| `tests/Unit` | Focused value/service behavior. |
| `docs` | Canonical architecture, contracts, runbooks and acceptance instructions. |
| `Development-CodexReports` | Exact phase/milestone implementation evidence. |
| `.github/workflows/ci.yml` | Remote quality and operational gates. |

The normal web request path is:

~~~text
route + middleware
→ Livewire component or controller
→ policy/authorization
→ validated input
→ explicit Action for mutation
→ transaction/locks/idempotency where required
→ domain record/event/outbox
→ committed database truth
→ projection/rendering
→ optional queued notification/broadcast
~~~

Do not place a critical multi-model transition directly in a Livewire component merely because the component is the caller.

## Identity and platform access

### Authority

- `User` owns credentials, email verification, sessions, account status, locale, timezone and platform grants.
- `Actor` is the participant named in domain history.
- An active registered human currently has one primary human Actor.
- `Actor.user_id` is stable and cannot be reassigned through ordinary UI or model editing.
- Accountless Actors are legitimate for organizations, systems and controlled future/simulation uses.
- Platform authority is granted to active verified Users through `PlatformAccessGrant`.
- Initial platform roles are Superadmin and GroupCreator; application code checks capabilities rather than assuming a role name.

### Principal files

- Models: `User`, `Actor`, `PlatformAccessGrant`, `PlatformAccessRequest`, `AccessInvitation`.
- Actions: `RegisterUser`, `RegisterAccessInvitedUser`, `IssueAccessInvitation`, `BootstrapSuperadmin`, platform access review Actions.
- Policies/UI: `ActorPolicy`, Platform Livewire components, authentication components and `EnsureAccountIsActive` middleware.

### Never do

- Do not infer platform administration from Actor kind, Group role or Membership.
- Do not require Group Membership merely to keep a User account.
- Do not change `Actor.user_id` in a generic update.
- Do not expose invitation/reset bearer tokens in logs or UI beyond their intended one-time flow.

## Actor Profile and semantic meaning

`ActorProfile` is presentation and progressive self-description attached to Actor, not authentication state. It is private by default. Account email is not automatically public Profile data.

The Concept kernel separates meaning from its contextual relationship:

~~~text
Concept: Chess
Actor A --has_skill--> Chess
Actor B --wants_to_learn--> Chess
Content C --about--> Chess
Group D --focuses_on--> Chess
~~~

Do not create duplicate “skill Chess”, “interest Chess” and “need Chess” Concepts. Use predicates and `ConceptAssertion`.

Profile capabilities include displayed identity/media, biography and profile facts, Concept-backed skills/interests/learning goals, optional skill proficiency, Need/Offer declarations, temporal preferences, completeness requirements and selective disclosure.

Profile is upstream state only. Editing a Profile must not create a Match, Relationship, Contract, Plan, Commitment or Fulfillment.

Principal code:

- Models: `ActorProfile`, profile image/disclosure models, `ActorProfileIntent`, Concept models.
- Actions: `app/Actions/Profile`, `app/Actions/Concepts`.
- Policies: Actor/Profile/Intent/Concept policies.
- UI: `app/Livewire/Profile`, `app/Livewire/Intents`, public people/Profile reference routes.

## Groups, Membership, invitations and Admission

A Group is a governed collaboration/community boundary. Membership is participation truth, while roles and permissions control authority within that Group.

Membership states are active, suspended, left and removed. Suspended Membership retains assigned roles but grants no authority. Leaving/removal revokes contextual authority. Readmission starts from the approved Admission path rather than silently restoring old authority.

Built-in Member and Owner roles have stable internal identities. Owner is additive to Member. The final active Owner cannot be removed, suspended, leave, or lose ownership without a dedicated ownership-transfer transaction.

The onboarding path is:

~~~text
invitation preview
→ register/login if needed
→ verify email
→ return to invitation
→ explicitly continue
→ redeem into Admission
→ accept exact required Agreement versions
→ submit/review/clarify/resubmit
→ approve
→ finalize Membership
~~~

An invitation does not grant Membership. An Admission does not grant ordinary GroupSpace access. Messages saying “approved” do not approve an Admission.

Principal code:

- Models: `Group`, `GroupMembership`, membership events, Group roles/requests, Group invitations/acceptances, `Admission`, Admission events, Group Agreements and acceptances.
- Actions: `app/Actions/Groups`, especially invitation redemption, Admission management/finalization, agreement management, role changes, Membership transitions and ownership transfer.
- Policies/UI: Group, GroupSpace and Admission policies; `app/Livewire/Groups` and `app/Livewire/Admissions`.

## Context kernel

A Context is a bounded collaboration and artifact environment answering who can enter, view, create, interact, manage and retain information. Implemented kinds include Personal, GroupSpace, Admission and managed Reference Contexts. Relationship, Proposal and Contract have their own bound Context records.

Context is not Membership, Profile disclosure, workflow state, Contract authority or Planner truth.

Key rules:

- Personal Context belongs to its active verified Actor.
- GroupSpace Context delegates to GroupSpace authorization.
- Admission Context allows candidate/reviewer collaboration before Membership.
- Reference Context allows broad authorized reading while designated maintainers manage official material.
- Terminal Context-backed domains may preserve historical reads while denying mutation.

Principal code:

- Models: `Context` and subtype bindings.
- Actions: `app/Actions/Contexts`.
- Policy: `ContextPolicy`.
- Support: `ContextScope`, `ContextTimeline`, `ContextNotificationRecipients`.

When adding a contextual capability, extend Context policy and reuse the existing Context identity. Do not create a duplicate per-Group or per-Relationship implementation of Content, Conversation, Planner or Submission.

## Content, Blueprints, Assets and evidence

Content is the independent human-facing artifact system. It has one home Context for authoring, lifecycle and authorization. Published Content may be placed in another authorized Context without copying its identity.

Two reference semantics must stay distinct:

~~~text
placement/presentation
→ may follow the current published revision

evidence/citation
→ exact Content UUID
→ exact sealed revision UUID
→ optional exact field/block/asset-placement/relationship target
~~~

Implemented capabilities include:

- Content identity and draft/published/archived lifecycle;
- immutable revisions and canonical hashes;
- versioned Content Definitions and safe fields;
- block documents;
- private authorized Assets with processing/readiness/rights state;
- safe presentation tokens and render templates;
- ordered revision-bound outline relationships with cycle protection;
- reusable versioned Content Blueprints;
- Reader, Studio, appearance, blocks and outline surfaces;
- reactions and exact-target annotations;
- immutable annotation disposition history;
- published Content Library;
- cross-Context placement;
- exact `ContentEvidenceReference`.

Current safe structured field types are short text, long text, number, date, boolean and select. Existing block types include paragraph, heading, quote, list, callout, divider, field, image, audio, video and file.

Content text is not a Contract, Plan, Fulfillment, approval, payment or JournalEntry. A capability card around Content calls the owning domain Action.

Principal code:

- Models: `SpaceContent` and related Definition, revision, block, relationship, annotation, reaction and render-template models; `ContentBlueprint*`, `ContentPlacement`, `ContentEvidenceReference`, `Asset`.
- Actions: `app/Actions/Content`, `app/Actions/Contexts`, and the established Content-related Group Actions.
- Support registries: `SpaceContentFieldRegistry`, `SpaceContentBlocks`, `SpaceContentPresentation`, Blueprint catalogs/config, publication evidence and outline services.
- Policies/UI: Content/Definition/Context policies and `app/Livewire/Contexts`.

`SpaceContent` naming is retained for compatibility. Do not perform a cosmetic rename before a deliberate migration boundary.

## Structured interactions

Annotations answer “what do I want to say about this target?” Structured interactions answer “what formal response am I submitting?”

~~~text
InteractionDefinition + immutable version
→ Submission
→ typed Responses and authorized Assets
→ Evaluation/review
~~~

Submissions bind to an exact published Content/interaction version, so later author changes cannot alter the historical questions answered.

Principal code:

- Models: `InteractionDefinition`, `InteractionDefinitionVersion`, `Submission`, `SubmissionResponse`, `Evaluation`.
- Actions: `app/Actions/Interactions`.
- Support: response registry/normalizers and evidence services.
- Policies/UI: Interaction, Submission and Evaluation policies; review queue/show components.

Do not create a second questionnaire engine for Admission, Planner or another module. Reuse the structured interaction system when the semantics are actually a Submission.

## Intent, matching, Relationships and journeys

An `ActorProfileIntent` expresses a current Need or Offer, including Concept, subject/arrangement, quantity/unit, location/route, temporal constraints, recurrence, visibility and optional value preferences.

Matching derives explainable candidates from authorized active Intents. It is discovery only. A candidate result creates no persistent authority by itself.

A Relationship is an explicit multi-Actor coordination boundary outside Group Membership. Its Context becomes writable only after required participant consent. A Relationship creates no employment, ownership, Contract, financing or payment merely by existing.

Domain Blueprints are versioned guided-entry recipes for proven compositions. They configure terminology and recommended capabilities but do not grant permission or execute arbitrary code.

Principal code:

- Models: `ActorProfileIntent`, `Relationship`, participants/context/events, `DomainBlueprint*`.
- Actions: Profile Intent Actions and `app/Actions/Relationships`.
- Support: `IntentMatchFinder`, match result and Domain Blueprint catalog/config.
- UI: Intent Directory/create/matches, Relationship pages and Journeys catalog.

## Proposal, Contract and exact acceptance

A Proposal is negotiable proposed terms among explicit parties. It has versions, decisions, events and a negotiation Context. Accepting a Proposal is not the same as activating a Contract unless the explicit Contract creation path runs.

A Contract owns exact party-specific authoritative terms. Contract versions are immutable. Each required party accepts the exact version. Activation occurs only through the Contract lifecycle Action after acceptance and effective-time rules are satisfied. Amendments create future versions; historical work remains bound to the governing version.

Human-readable terms may use a sealed Content revision, but Contract models remain operational authority.

Principal code:

- Models: Proposal, versions/parties/decisions/events/context; Contract, versions/parties/acceptances/events/context.
- Actions: `app/Actions/Proposals`, `app/Actions/Contracts`.
- Policies/UI: Proposal and Contract policies and Livewire components.

Never infer acceptance from chat text, annotations, button visibility or a generic status update.

## Commitment and Fulfillment

A Commitment says what must happen. Fulfillment records what actually happened against it.

Commitments may create/bind Plans through the explicit commitment-planning Action. The Commitment remains obligation truth; Planner remains schedule/execution truth.

Fulfillment records actual quantity, duration/time, status, notes and exact Content/Asset evidence where appropriate. Beneficiary review is explicit: accept, reject or request clarification. Disputes and corrections have their own lifecycle.

Principal code:

- Models: `Commitment`, events and Plan binding; `Fulfillment`, review and dispute.
- Actions: `app/Actions/Commitments`, `app/Actions/Fulfillments`.
- Support: `CommitmentProgress`.
- Policies/UI: Commitment/Fulfillment policies and pages.

## Planner

Planner is authoritative for intended schedules and materialized occurrences.

Core records:

- `Plan`;
- `PlanParticipant`;
- `PlanScheduleRule`;
- `PlanOccurrence`;
- `PlanReminder`;
- Plan and occurrence lifecycle events;
- optional `CommitmentPlanBinding`.

Supported foundations include one-time and recurring activity, timezone-aware materialization, personal and Context-scoped activity, participants, Today/List/Calendar projections, lifecycle transitions, evidence and reminders.

Rules:

- Store true instants in UTC and interpret local input using explicit IANA timezone.
- Do not silently use a displayed civil calendar value as storage truth.
- Scheduled start/end and actual start/end are different facts.
- A missed window must not fabricate execution history.
- Home/Today and calendar displays never create Planner truth.
- A calendar click may prefill a form but must not create a Plan without the normal validated Action.
- Evidence attachment rechecks Plan/Context/Asset authorization.

Principal code:

- Actions: `app/Actions/Planner`.
- Policy/UI: `PlanPolicy`, `app/Livewire/Planner`.
- Scheduled maintenance: `planner:materialize`, daily horizon materialization, reminder emission.

The preserved Planner candidates contain useful execution/evidence/calendar work but are not accepted wholesale. Review execution lifecycle, temporal presentation, query authorization/caps, calendar navigation and seeded creation as separate modules.

## Personal accounting and economic consequences

### Personal Accounting

The accounting kernel includes Monetary Units, Ledgers, Accounts, immutable balanced Journal Entries/Lines, corrections/reversals, and derived summaries.

Friendly actions such as opening balance, expense, income and transfer call explicit accounting Actions. Users need not understand debit/credit terminology, but the records must remain balanced.

Rules:

- Use decimal-safe money handling; never binary floats.
- Every amount has an explicit Monetary Unit.
- Posted entries are immutable.
- Corrections use reversing/correcting entries.
- Posting is transactional and idempotent at source boundaries.
- Never sum unlike currencies without explicit exchange-rate evidence.
- A cached balance is never source of truth.

### Financial Obligation and Settlement

An accepted Fulfillment or another defined economic event may create a `FinancialObligation`. A `Settlement` records a proposed/confirmed/rejected payment claim. Explicit bridge Actions optionally post those consequences into Personal Accounting.

Do not directly mutate balances when Fulfillment is accepted or a Settlement is confirmed.

Principal code:

- Models: `MonetaryUnit`, `Ledger`, `Account`, `JournalEntry`, `JournalLine`, `FinancialObligation`, events and `Settlement`.
- Actions: `app/Actions/Accounting`, `app/Actions/Financial`.
- Support: `MoneyAmount`, accounting/contract financial summaries and Monetary Unit catalog.
- Policies/UI: Ledger, obligation and Settlement policies; Accounting and Financial Livewire pages.

## Home, timeline, notifications and realtime

Home/Today is a policy-filtered projection over existing authoritative records. It may show today’s occurrences, waiting actions, Needs/Offers, Relationships, Groups, accounting summaries, obligations and recent activity. It owns no duplicate persistence and does not invent unread or lifecycle state.

Context Timeline is likewise a source-linked projection over durable events. Every entry must identify its source.

Notification flow:

~~~text
transactional domain Action
→ transactional idempotent NotificationOutbox
→ commit
→ queued delivery
→ durable database notification/read state
→ authorized private user broadcast
→ client refresh
~~~

Broadcast failure never erases committed domain truth. Private channel authorization requires the same active verified User identity.

Principal code:

- `HomeTodayProjection`, `ContextTimeline`, `DomainNotificationProjector`.
- `NotificationOutboxWriter`, dispatcher, `DeliverNotificationOutbox` job.
- `UserInboxChanged` event and `routes/channels.php`.
- Notification and Home Livewire components.

## System Manual and documentation

Developer/architecture Markdown remains repository authority. The user-facing IET System Manual is also materialized as ordinary versioned Content in a managed Reference Context.

Every material user-facing milestone must update both:

1. developer/architecture documentation;
2. `App\Support\SystemManualContent`.

Manual instructions use:

~~~text
WHO
→ WHERE in UI
→ WHAT is visible
→ WHAT to click/select/type
→ WHAT durable result is created
→ WHO can see it
→ WHAT it does not imply
~~~

Repository source synchronization is explicit through `php artisan system-manual:sync <owner-email>`. Normal seeding must not silently overwrite maintainer-edited official revisions.

English is the canonical editorial source. Persian, Arabic and Simplified Chinese UI catalogs exist; translated manual editions require their own review lifecycle and must not be labelled native-reviewed merely because they were generated.

## Canonical story world

Use the same examples across docs, tests and browser acceptance:

- Diego Santos: office operator and Maple Housing Office owner.
- Alice Morgan: Riverside property owner needing construction and capital.
- Bob Rahimi: construction service provider/worker.
- Carol Chen: capital provider/collaborator.
- Maple Housing Office: governed Group.
- Riverside Home Project: cross-module composition proof.

Paid-work proof:

~~~text
Alice and Bob Relationship
→ versioned Contract for selected 08:00–17:00 workdays
→ exact acceptance
→ work and payment Commitments
→ materialized Planner Occurrences
→ actual start/end and evidence
→ reviewed Fulfillment
→ Financial Obligation
→ Settlement
→ optional explicit accounting posting
~~~

The system must be able to explain scheduled, worked, accepted, earned, paid, outstanding and disputed values from linked authoritative records.

## How to locate the correct place for a change

Use this decision table before editing:

| Requested change | Start inspection here | Usually also inspect |
| --- | --- | --- |
| Login, registration, verification | Auth Livewire/controllers and `app/Actions/Auth` | User/Actor provisioning, middleware, invitation tests. |
| Platform permission | Platform grants/requests and Actor/User policies | AppServiceProvider gates, platform UI, audit tests. |
| Group/member behavior | Group/Admission Actions and policies | agreements, invitations, membership events, Context access. |
| Profile field | Profile model/catalog/Action | disclosure, public reference, validation, all locales. |
| Semantic category/skill | Concept Assertions and predicates | Concept policies, hierarchy/closure, Profile/Content consumers. |
| Content field/block | Content registry/normalizer and revision Action | Blueprint config, Reader/Studio, hashing, evidence, locale. |
| Questionnaire/application | InteractionDefinition/Submission | exact version binding, evaluator policy, Assets. |
| Message/chat | Context Conversation | Context policy, attachments, Timeline; never domain transitions. |
| Need/Offer or match | ActorProfileIntent/IntentMatchFinder | visibility policy, Relationship handoff, explanations. |
| Relationship change | Relationship Action/policy | participants, Context lifecycle, events/Timeline. |
| Proposed terms | Proposal Actions | sealed terms Content, parties, Conversation. |
| Binding terms | Contract Actions | exact version acceptance, effective time, Commitments. |
| Work obligation | Commitment/Fulfillment Actions | Planner binding, evidence, review, finance bridge. |
| Schedule/calendar | Planner Actions/models | timezone, recurrence, occurrence policy, Home/Today. |
| Money or balance | Accounting/Financial Actions | MonetaryUnit, idempotency, reversals, obligation source. |
| Dashboard item | Projection service | every source policy and source route. |
| Notification | domain event/outbox projector | recipients, idempotency, queue, database fallback, channel auth. |
| Translation/UI copy | English key plus all locale catalogs | placeholder parity, rendered tests, RTL/mobile. |
| Seed/demo data | specific opt-in seeder | domain Actions, idempotent rerun, environment restriction. |

## Safe change procedure

For every change, follow this order:

1. Restate the requested user-visible outcome.
2. Identify the owning domain and authoritative record.
3. State what the change must not imply.
4. Inspect existing models, Actions, policies, routes, UI, tests, migrations and docs in that domain.
5. Search for an existing component, registry, Action or projection before creating anything.
6. Identify upstream dependencies and downstream consumers.
7. Define authorization for view, create, mutate, approve and historical read separately.
8. Define lifecycle, concurrency, idempotency and failure behavior.
9. Define immutable evidence/provenance requirements.
10. Define localization, timezone, calendar, mobile, RTL and accessibility effects.
11. Create a focused branch from the current accepted assembly/integration SHA.
12. Add or update the narrowest regression tests first or alongside the implementation.
13. Implement through the authoritative Action and policy boundaries.
14. Run focused tests after each coherent change.
15. Run Pint on changed PHP, PHPStan, relevant browser/build checks and then the full gate.
16. Update developer docs and System Manual source for material user-facing behavior.
17. Record exact SHA, commands, results, migrations, limitations and deferred browser checks.
18. Integrate through a reviewed PR; never force-push shared history or rewrite an RC.

## Change recipes

### Add a normal domain field

First decide whether it is authentication data, Profile data, Content configuration, a typed domain invariant, or a derived projection. Do not put every new value into JSON.

Typical sequence:

1. append-only migration;
2. model cast/fill behavior following sibling conventions;
3. validation at the Action/Livewire boundary;
4. authorization and privacy review;
5. Action support if it affects lifecycle or multiple records;
6. UI plus all locale keys;
7. factory/test fixture update;
8. persistence, tamper and visibility tests;
9. documentation/manual update.

### Add a lifecycle transition

Use a named Action. Validate current state under transaction and row lock where races matter. Recheck acting User/Actor authority. Write the durable state and event atomically. Make retries idempotent when the caller may repeat. Notify only after durable truth exists. Test allowed, forbidden, stale and concurrent paths.

### Add a cross-module relation

Link exact source identities and preserve provenance. Decide which module owns the relation and which owns each fact. Prefer explicit foreign keys/binding records over copying values. Test that deleting, archiving or superseding one side does not corrupt immutable history. Never introduce a universal polymorphic “everything relationship” merely for convenience.

### Add a new Content field type

Extend the bounded field registry, versioned schema validation, payload normalizer, canonical hashing, authoring control, Reader output, accessibility behavior, all locales and publication/evidence tests. Existing published Definition versions must keep their historical interpretation.

### Add configurable fields to a domain form

Do not immediately add arbitrary JSON to Plan, Contract or another authoritative model. First audit Content Definitions, Interaction Definitions and Domain Blueprints. Keep authoritative fields in the owning model/Action. Pin a versioned safe form definition, validate answers server-side, preserve historical interpretation, separate drafts from submitted evidence, and prove the abstraction in two unrelated domains before calling it generic.

### Add a Planner/calendar capability

Keep UTC/Gregorian storage and explicit IANA timezone. Distinguish date-only, wall time and instant. Calendar navigation is a projection over authorized occurrences. Filter authorization before limits. Validate URL-selected periods. Bound queries. Handle DST repeated/missing hours. A slot may prefill creation but cannot persist or infer recurrence/Contract authority.

### Add financial behavior

Identify whether the value is an estimate, Contract term, obligation, Settlement or accounting entry. Use decimal-safe value objects and explicit Monetary Unit. Create a JournalEntry only through an idempotent balanced posting Action. Never rewrite posted history or convert old records because a user changed their default currency.

### Add a notification

Start from a committed domain event, derive exact authorized recipients, write an idempotent outbox record in the transaction, deliver after commit, persist the notification, then optionally broadcast. Test duplicate dispatch, recipient revocation, queue retry and reconstruction without realtime.

### Add or change a seeder

`DatabaseSeeder` remains minimal, local/testing-only and repeatable. It may ensure the standard test User, Actor and active Superadmin grant, plus the documented System Manual materialization. Domain demonstrations belong in explicit opt-in seeders. Seeders should use normal Actions/invariants, be idempotent and never delete unrelated data. Do not add `migrate:fresh` assumptions.

### Improve UI without changing authority

Identify the exact existing Action and policy. Change discoverability, wording, layout, state feedback or progressive disclosure without moving business rules into the browser. Preserve route and authorization checks. Test rendered output, loading/empty/error states, phone width, keyboard/focus, reduced motion, RTL and all supported locales.

## Testing and quality gates

Use the narrowest test while developing, then broaden.

Common commands:

~~~text
php artisan test --compact tests/Feature/RelevantTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
php artisan view:cache
npm run build
php artisan test --compact
git diff --check
git status --short
~~~

For a release or milestone, also require when applicable:

- MySQL migration forward and rollback/reapply smoke;
- SQLite migration/operational smoke;
- scheduler and database-queue smoke;
- backup/restore drill;
- npm audit and Composer audit;
- authorization/privacy regression tests;
- concurrency/idempotency tests;
- browser checks on desktop and phone;
- English, Persian, Arabic and Simplified Chinese rendering;
- RTL, keyboard/focus and accessibility review.

Never say a test or gate passed unless its command or remote CI actually ran. A clean diff is not runtime validation.

## Git and GitHub discipline

- Never develop directly on `main`, an immutable RC, or the shared assembly branch.
- Fetch before starting and create a coherent module branch from the exact accepted SHA.
- Use `pull --ff-only` to avoid accidental merge commits from pulls.
- Keep one independently reversible concern per PR.
- Prefer atomic commits with behavioral subjects.
- When selectively taking an isolated existing commit, use traceable cherry-picking such as `cherry-pick -x`.
- For mixed commits, record the source SHA and selected paths/hunks in the PR/report.
- Do not merge aggregate experimental branches merely because they contain desired features.
- Never force-push shared history.
- Never rewrite a recorded checkpoint to hide a correction.
- Require green CI and resolved review before merge.
- Record exact source SHA, resulting SHA, migrations, tests, known limitations and browser evidence.
- Freeze a new immutable RC after corrections; do not move old RC refs.
- Stable production tags are distinct from development checkpoints.

Current selective integration topology:

~~~text
integration/ideal-v1 @ 891b333
        ↓
codex/ideal-v1-selective-assembly
        ├── runtime foundation @ 891b333
        ├── S0/M00 certification and governance checkpoints
        └── fetch current remote HEAD before work
        ↓
codex/review-<one-module>
        ↓ focused implementation + validation
PR → codex/ideal-v1-selective-assembly
        ↓ complete cumulative gate
new release candidate
~~~

## Prioritized pre-publication review

The active review model is no longer one mandatory M00→M15 pipeline.

Use this structure:

1. **F1 Shared Temporal Fabric recovery**
   - profile-aware Temporal Kernel;
   - permanent Ambient Capability Rail;
   - shared Fractal Calendar Fabric;
   - Capability Launcher/composition contract.
2. **Independent capability-node reviews**, prioritized by current user value:
   - Planner;
   - Personal Accounting / Finance;
   - Agreement / Contract authority;
   - Need / Offer / Relationship;
   - Group / Invitation / Admission / Membership;
   - Content / Assets / Evidence;
   - Submission / Evaluation;
   - Notifications / Realtime / Home-Today.
3. **Explicit seam reviews** after both endpoint nodes are accepted:
   - Planner ↔ Finance;
   - Planner ↔ Contract/Commitment;
   - Contract/Fulfillment ↔ Financial Obligation;
   - Settlement ↔ Accounting;
   - each temporal node ↔ Calendar;
   - each useful node ↔ Ambient header;
   - relevant views ↔ Capability Launcher.
4. **Human-centered composition review**, proving a flow may stop at a simple level or extend only when needed.
5. **System-wide polish and release operations**.

The review order is a priority order, not a claim that every earlier node is a runtime prerequisite.

Configurable form extensions, Generic Workflow, Reputation, Recommendations and AI Copilot remain post-v1 unless a concrete current journey cannot be released safely without them.

Every node/seam uses a dedicated review branch, living report, remote gate, owner browser acceptance before merge, post-merge CI and recorded checkpoint. The architecture contract is in `docs/CAPABILITY_MESH_ARCHITECTURE.md`; active recovery details are in `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`.

At this snapshot, GitHub protection covers `main` but not necessarily the selective-assembly branch. Until repository rules are extended, PR-only and no-force-push discipline on the assembly branch is procedural and must be followed deliberately.

## Operations required for publication

Production configuration must include:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `IET_RELEASE_PROFILE=full`;
- a real secret `APP_KEY`;
- HTTPS `APP_URL`;
- production database, mail and private storage;
- supervised queue workers;
- a supervised scheduler invocation;
- automated backups and a tested isolated restore;
- failed-job visibility and retry ownership;
- application/error monitoring;
- structured logs without secrets or private uploaded content;
- rate limiting and abuse controls;
- an incident and rollback procedure.

The nine-day learning period should use existing durable domain history and bounded operational logs. Do not add invasive analytics merely to gather more data. Each observed issue should record date, exact deployed SHA, actor/role, route, steps, expected result, actual result, severity, evidence, affected domain, privacy/security impact, workaround, regression test and correction SHA.

## Known boundaries and deferred work

- Organization/system Actors and explicit multi-Actor acting authority are future work.
- Generic Workflow extraction is post-v1 and requires two proven unrelated domains.
- Reputation must derive from validated evidence and must not become one opaque universal score.
- Recommendations must be authorized, explainable and controllable.
- AI Copilot is deferred and may only prepare validated drafts.
- Financial Laboratory and real external money/custody are separate, controlled future areas.
- Legacy `Story`, `StoryRole`, `Responsibility` and construction seed assumptions must not be extended as target architecture.
- Content search/discovery, archive recovery, nested authoring and some audience/interaction refinements remain incomplete.
- Configurable domain form extensions are a proposal, not an accepted or implemented v1 capability.

## Instructions for an AI without repository access

An offline AI must not fabricate exact class methods, columns, routes, migration names or passing-test claims. It can safely provide:

- a change contract;
- owning-domain analysis;
- authority and privacy boundaries;
- expected files by directory/class responsibility;
- migration/test strategy;
- pseudocode;
- acceptance criteria;
- questions that must be answered by repository inspection.

It should label assumptions explicitly and hand the following inspection list to the implementing developer:

~~~text
1. Confirm current branch and exact SHA.
2. Read AGENTS.md and matching .ai/rules.
3. Confirm CURRENT_STATE and active release/milestone contract.
4. Search existing models, Actions, policies, UI, tests and migrations.
5. Confirm installed package versions before using an API.
6. Confirm current database constraints and indexes.
7. Confirm route middleware and policy calls.
8. Confirm locale keys and rendered behavior.
9. Run focused tests, then complete gates.
10. Report exact evidence and unresolved uncertainty.
~~~

## How the owner should request a change

Use this template to obtain a safe, reviewable result:

~~~text
Change objective:
<What should the user be able to do?>

User and context:
<Who acts, in which Context/Group/Relationship, and on which device/locale?>

Durable result:
<Which authoritative fact should exist after success?>

Must not imply:
<Membership, approval, acceptance, Contract, Fulfillment, payment, accounting, etc.>

Visibility and authority:
<Who may view, create, edit, approve, or see history?>

Lifecycle:
<Draft, submitted, accepted, rejected, cancelled, archived, corrected, etc.>

Evidence/history:
<What must remain immutable and traceable?>

UX expectation:
<Where it appears, required/optional fields, mobile/RTL/accessibility expectations.>

Compatibility:
<What existing behavior/data must remain unchanged?>

Acceptance examples:
1. <Successful case>
2. <Forbidden case>
3. <Stale/concurrent case>
4. <Mobile/localized case>

Delivery instruction:
Inspect first. State the owning domain and affected files. Reuse existing Actions,
policies, components and registries. Add regression tests. Do not broaden scope or
change architectural authority without explicit approval.
~~~

Short owner prompts can use this form:

~~~text
Review <feature> on <exact branch/SHA>. Do not implement yet. Identify its owning
domain, authority boundaries, dependencies, conflicts, source commits, tests and a
smallest-safe integration plan.
~~~

or:

~~~text
Implement only <one behavior> from <source SHA> on a new branch from
codex/ideal-v1-selective-assembly. Preserve <listed invariants>. Do not merge the
source branch wholesale. Run <focused tests> and the required quality gates, then
report exact files, SHAs, commands, results and remaining browser checks.
~~~

## Final developer checklist

Before declaring any change complete, answer yes to all applicable questions:

- Is the owning domain unambiguous?
- Is every non-implication documented?
- Are User, Actor, Membership, Context and platform authority still distinct?
- Does the mutation pass through an explicit Action?
- Is authorization checked server-side at view and mutation boundaries?
- Are transactions, locks and idempotency adequate?
- Is immutable history preserved?
- Are cross-module links provenance-preserving rather than copied truth?
- Are money, timezone and calendar semantics explicit?
- Are result limits applied after authorization or safely in authorized SQL?
- Is realtime optional rather than authoritative?
- Are new UI strings present in all supported locales?
- Is phone, RTL, keyboard/focus and error-state behavior acceptable?
- Do focused regression tests cover success, forbidden, stale and tampered cases?
- Did full static, test and build gates actually run?
- Were migrations tested forward and, where supported, rollback/reapply?
- Were developer docs and System Manual source updated when behavior changed?
- Is the Git history traceable, reviewable and free of force-pushed checkpoint rewrites?
- Is the exact remaining uncertainty stated honestly?

If any answer is unknown, the change is not ready for release.
