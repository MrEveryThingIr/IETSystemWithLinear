# IET Production Roadmap

## Purpose

This roadmap is the authoritative implementation order from the current validated kernel to a production-ready IET platform.

It is intentionally phase-gated.

A phase is not complete because code was written. A phase is complete only when its acceptance gate is met and the repository records the evidence.

Agents must not implement later phases speculatively.

## Baseline

Starting implementation baseline:

- branch: `feat/group-spaces-communication`;
- code baseline: `f57ee430f96afcdb1ecc32f5b644fcb057dae6f4`;
- verified locally:
  - 273 tests passed;
  - 1386 assertions;
  - PHPStan clean;
  - Pint clean;
  - Vite build clean.

The Phase 0 documentation synchronization was subsequently committed at `dee86677cdfd95b6b2887843cba699b21b07f6f0`, which became the Phase 1 starting HEAD.

Phase 1 closure validation reported by the human owner on 2026-09-21:

- focused onboarding/hardening gate: 41 tests / 216 assertions;
- full suite: 282 tests / 1437 assertions;
- PHPStan: no errors;
- Pint: passed;
- Vite production build: passed;
- `git diff --check`: clean;
- browser onboarding/hardening behavior accepted by the human owner.

The next implementation phase after the Phase 1 closure commit is Phase 2.

## Global phase rules

Every phase must:

1. begin from a clean, synchronized branch;
2. inspect existing code before proposing replacements;
3. record exact included and excluded scope;
4. preserve current invariants unless an approved architecture decision replaces them;
5. use migrations rather than destructive schema resets for durable data;
6. add focused tests for all new invariants;
7. run the narrowest useful tests while developing;
8. pass the required phase validation before completion;
9. record the implementation report under `Development-CodexReports/`;
10. stop for human review at the exit gate.

Mandatory final validation for code-bearing phases unless explicitly inapplicable:

~~~text
php artisan test --compact
vendor/bin/phpstan analyse
vendor/bin/pint --dirty --format agent
npm run build
git status --short
~~~

Documentation-only phases may omit runtime commands if no runtime file changed, but must report that honestly.

## Cross-cutting production tracks

These do not wait until the final phase.

Every relevant phase must consider:

- authorization;
- privacy;
- audit/evidence;
- concurrency/idempotency;
- accessibility;
- mobile/responsive UX;
- localization;
- pagination/query bounds;
- observability;
- queue/retry behavior;
- data migration/rollback;
- abuse/rate limiting;
- backups/recovery implications.

## Phase 0 — Canonical architecture and execution contract

### Purpose

Make the repository—not chat history—the durable source of truth.

### Deliverables

- current Project Compass reconciled;
- current-state snapshot;
- target architecture;
- Concept Kernel design;
- Financial Architecture design;
- production roadmap;
- Phase 1 implementation contract;
- agent/development circuit updated;
- stale repository rules reconciled.

### Excluded

- runtime/domain code changes;
- database migrations;
- feature changes.

### Exit gate

- canonical documents are committed;
- current branch is synchronized locally;
- no runtime behavior changed;
- human owner accepts the roadmap as the working contract.

## Phase 1 — Production invitation, registration and admission journey

**Status: complete at the Phase 1 closure commit.**

Detailed contract and completion evidence: `docs/PHASE_01_INVITATION_ONBOARDING.md` and `Development-CodexReports/phase-01-invitation-onboarding-report.md`.

### Purpose

A completely new person should be able to receive one invitation link and complete the allowed onboarding journey without developer assistance.

### Core outcome

~~~text
Invitation
→ understandable preview
→ register/login
→ email verification
→ automatic return
→ Admission progress
→ agreements/requirements
→ submit/review
→ membership
→ welcome/group home
~~~

### Exit gate

- end-to-end automated tests pass;
- manual browser test with a fresh user succeeds;
- invitation links are safe, comprehensible, and mobile usable;
- no candidate receives Group membership before the Admission rules allow it;
- mail/link handling is documented for production.

## Phase 2 — Delivery and operations baseline

**Status: complete at the provider-neutral operational baseline.** Completed branch: `feat/phase-02-delivery-operations`. Detailed contract: `docs/PHASE_02_DELIVERY_OPERATIONS.md`.

### Purpose

Establish the operational floor before the platform expands significantly.

### Deliverables

- CI workflow with required test/static/build gates;
- environment/deployment documentation;
- queue/scheduler operating model;
- transactional email configuration/runbook;
- structured error reporting/monitoring plan;
- database backup procedure;
- restore drill;
- failed-job handling;
- dependency/security update process;
- basic rate limiting/abuse controls on auth/invitation endpoints;
- production storage/media configuration guidance.

### Exit gate

A release candidate can be deployed, observed, backed up, restored, and diagnosed without relying on developer memory.

## Phase 3 — Concept Kernel

**Status: complete.** Completed branch: `feat/phase-03-concept-kernel`. Detailed contract: `docs/PHASE_03_CONCEPT_KERNEL.md`. Architecture: `docs/CONCEPT_KERNEL.md`.

### Purpose

Create the semantic layer before large template/catalog growth.

### Deliverables

- ConceptVocabulary;
- Concept;
- ConceptLabel;
- ConceptScheme;
- Scheme Membership;
- polyhierarchical edges;
- closure;
- relation registry and relations;
- generic Concept Assertions;
- authorization;
- merge/deprecation lifecycle;
- tests for cycles, multiple parents and assertions.

### Proof cases

- Chess under two parents;
- one Actor simultaneously has_skill and wants_to_learn Chess;
- Content classified as about Chess.

### Excluded

- recommendation engine;
- AI auto-taxonomy;
- giant seeded ontology.

### Exit gate

Concept semantics are reusable by both Actor Profile and Content without duplicate category records.

## Phase 4 — Actor/Party and progressive Profile

**Status: complete and human-owner accepted on 2026-09-22.** Detailed contract: `docs/PHASE_04_ACTOR_PROFILE.md`.

Final runtime baseline before documentation-only closure:

`20e7c2834fca74b652f89195094b70f86b454f80`

Final CI proof on GitHub Actions run `35700986122`:

- **331 tests / 1719 assertions**;
- PHPStan clean;
- Pint **171 files** clean;
- production frontend build green;
- migrations, scheduler and database-queue smoke green;
- SQLite backup → restore smoke green;
- npm and Composer security audits green.

Phase 4 delivers the professional Actor Profile foundation, private media/display-avatar pipeline, Concept-backed skills/interests/learning goals, recurring Profile Need/Offer intent declarations, temporal preferences, purpose-specific completeness, selective disclosure, read-first/on-demand editing, reusable participant avatar+identity presentation, optional skill proficiency (0–100%) and optional intent importance/urgency (0–100%).

Architectural boundaries remain explicit: recurring Profile declarations are current intent rather than Planner Occurrences (Phase 11); Profile Need/Offer records are not Matches (Phase 13); selective disclosure is live access rather than immutable Contract evidence (Phase 14); and Phase 4 introduces no generic Context state.

### Exit gate

**Passed.** Final local/browser acceptance on synchronized HEAD `ec1961b` confirmed the migration, **45 focused tests / 237 assertions**, **331 full tests / 1719 assertions**, PHPStan, Pint, Vite build, clean diff/tree, and browser/mobile/RTL behavior.

## Phase 5 — Generic Content Context

**Status: complete and human-owner accepted on 2026-09-22.** Detailed contract: `docs/PHASE_05_GENERIC_CONTENT_CONTEXT.md`.

Frozen runtime candidate: `34bd6b8957e4ecc2b0474bc0b7d163ae010dc749`.

Remote proof: **344 tests / 1807 assertions**, PHPStan clean, Pint **216 files**, Vite build green, fresh migrations and operational smoke green, SQLite backup → restore green, npm/Composer audits green.

Human owner validation passed: Context migrations applied on the existing database; focused gate **25 tests / 156 assertions**; full suite **344 / 1807**; PHPStan/Pint/Vite clean; browser My Content creation accepted. Phase 6 is unblocked.

### Purpose

Remove the architectural requirement that all Content belong to a GroupSpace.

### Deliverables

- Context abstraction;
- GroupSpace-backed Context compatibility;
- Personal Context;
- Admission Context;
- context authorization contract;
- context-scoped collaboration authorization seam, proving that an Admission candidate/reviewer can collaborate before Membership without receiving ordinary Group access;
- migration/compatibility layer for current SpaceContent;
- no destructive mass rename until migration is proven.

### Proof cases

- existing Group Content behaves unchanged;
- personal private Content exists without fake Group;
- Admission Content is accessible to candidate/reviewer before Membership.

### Exit gate

Content can safely exist in at least Group, Personal, and Admission contexts.

## Phase 6 — Content Blueprints and unified productized authoring

**Status: complete and human-owner accepted for roadmap progression on 2026-09-22.** Runtime candidate: `ad07445b16a708b4efd67461f5cef12201ffa8b1`. Closure HEAD before documentation update: `b33bdcf`. Detailed contract: `docs/PHASE_06_CONTENT_BLUEPRINTS.md`.

### Purpose

Turn the powerful low-level Content engine into convenient reusable authoring and converge Personal/GroupSpace/Admission authoring onto one advanced Content experience.

### Deliverables

- versioned ContentBlueprint;
- Definition binding;
- initial Blocks;
- RenderTemplate binding;
- Concept classification defaults;
- interaction defaults;
- explicit Blueprint version provenance;
- upgrade/clone semantics;
- Blueprint catalog/search;
- Quick → Guided → Advanced authoring;
- one generic Context Studio/Reader capability surface rather than a second simplified Content system;
- immutable revision/block/asset evidence locators for future verification/reputation;
- conversion of English workbook demonstration into a reusable Blueprint pattern.

### Initial Blueprints

- Note / Diary;
- Post;
- Article;
- Activity / Report;
- Evidence / Work Sample;
- Media Album;
- Book / Booklet;
- Lesson;
- Workbook Page;
- Questionnaire shell.

### Exit gate

Passed. Automated/runtime conditions were satisfied on `ad07445b16a708b4efd67461f5cef12201ffa8b1`. On 2026-09-22 the owner synchronized `b33bdcf`, applied the three Phase 6 migrations to the existing database, passed 20 focused tests / 121 assertions and the full 364 / 1934 suite, PHPStan, Vite, clean diff/tree checks, and performed a non-exhaustive browser review that found no blocking defect. Non-blocking Content-view polish is deferred in `docs/CURRENT_STATE.md`; it does not keep Phase 6 open.

## Phase 7 — Submission / Response / Evaluation

**Status: runtime technically complete / remote-CI green; final owner browser/mobile/RTL acceptance pending.** Detailed contract: `docs/PHASE_07_SUBMISSION_EVALUATION.md`. Current runtime candidate: `bd45c994535a2045fde7e23c924d103aaa666c25` (GitHub Actions run `35824650618`: 394 tests / 2092 assertions; changed-file Pint 307 files; PHPStan/Vite/ops/backup/security green; Phase 7 migrations rollback/reapply green). The post-7E correction makes submitted reviewer work visible from the Context content header as `Review submissions (:count submitted)`.

### Purpose

Support structured interactions that annotations cannot represent correctly, while deliberately shaping the kernel so the same Submission/Response/Evaluation components can later appear naturally inside the conversation-first Admission experience without making messages authoritative.

### Deliverables

- versioned InteractionDefinition;
- Submission;
- Response;
- attachments/evidence through authorized reusable Assets rather than public message-owned blobs;
- requirement/evidence response patterns usable by Admission v2;
- draft/submit/withdraw lifecycle;
- reviewer/evaluator authorization;
- Evaluation/feedback;
- exact Content/interaction-version binding;
- conversation-embeddable structured interaction components that do not depend on Conversation or realtime infrastructure.

### Proof cases

- school exam;
- employment application.

### Exit gate

Automated/runtime conditions are green on current candidate `bd45c994535a2045fde7e23c924d103aaa666c25`. Both proof cases work without abusing annotations or creating separate form engines; submitted evidence remains historically exact after newer Content/interaction versions; pre-Membership Admission isolation is preserved; and the application components can later be composed into Conversation without changing their domain semantics. The owner's manual feedback exposed insufficient reviewer discoverability, so Phase 7 now surfaces a persistent review entry/count from Context Content and protects that behavior with regression coverage. Final browser/mobile/RTL owner acceptance of this corrected UX remains required before formal Phase 7 closure or Phase 8 runtime work.

## Cross-cutting gate — Documentation as Content

This is not a new domain kernel or a parallel documentation product. It is a standing delivery rule for every remaining phase.

Before/alongside Phase 8 and later milestones:

- review implemented kernels in human learning order and maintain a complete educational manual;
- materialize the user-facing manual as normal versioned Content using existing Content/Blueprint/Reader capabilities;
- keep official system-authored editions visually identifiable and clean;
- preserve private/community annotations, questions, replies, voice/image/video additions as overlays unless an authorized maintainer accepts them into a new official revision;
- add explicit feedback disposition/provenance so a later official revision can identify which exact questions/corrections/ideas it addressed or incorporated;
- begin with complete English manual content, then add Persian through a separately reviewable translation lifecycle; Arabic and Simplified Chinese follow after the translation workflow is proven;
- document every important object with purpose, authority, Context, lifecycle, relationships and negative guarantees;
- document every workflow as WHO → WHERE → WHAT → ACTION → durable RESULT → WHO CAN SEE IT;
- update the manual in parallel with runtime changes and do not formally close material user-facing milestones while their behavior is undocumented;
- introduce contextual Reader capability providers incrementally as domains exist, always reusing existing policies/Actions;
- maintain a clean-reading mode so contextual controls never obscure the origin artifact;
- productize Actor/Context Blueprint cloning/versioning/editing when it becomes the next authoring blocker, reusing the existing Blueprint kernel.

Initial manual coverage should include identity/Actors, Groups/Membership/roles, Contexts, Content/Definitions/Blueprints, revisions/permalinks/evidence, annotations/media, Submission/Response/Evaluation, and Admission/invitation boundaries.

A production-wide official manual needs a general system/reference Content access solution; never bypass authorization specifically for documentation.

Before beginning a major new kernel after Phase 7, use the manual/audit pass to re-evaluate the remaining roadmap against the connected-life north star. Reordering or splitting later phases is allowed when the audit exposes a clearer dependency, but canonical boundaries (identity/authority, immutable evidence, conversation-not-authority, domain truth outside Content) must remain intact unless the human owner explicitly changes them.

## Foundation F0 — Clean pre-AI office foundation

**Status: active on `integration/ideal-v1`.**

Purpose: establish the cumulative Ideal-v1 trunk from the accepted pre-AI baseline while preserving the revised standalone Access Invitation, office-alpha Intent wizard/directory, Content/Context/manual foundations and Phase 7 structured interactions.

Included:

- standalone Access Invitation → welcome → register → verify → Get Started;
- Group Invitations for existing verified users;
- Need/Offer Intent capture for Property/Good/Service/Capital/Collaboration;
- non-binding cash/mixed-value negotiation preference;
- permission-aware read-only Intent Directory;
- `office_alpha` focused navigation profile;
- Documentation-as-Content;
- removal of unused AI-assistance / Development-Origin runtime.

Exit: remote CI green, canonical docs synchronized, F0 report/worksheet recorded.

## Phase 8 — Progressive Intent Journey v2

Purpose: turn the first Intent wizard into a reusable decision graph without prematurely building a generic wizard engine.

Proof paths:

- buy/sell a simple product;
- buy/rent property;
- request/provide a service;
- paid work / hire;
- project collaboration combining property, service and capital.

Rules:

- previous answers determine later questions/blocks;
- irrelevant fields stay hidden;
- wizard state is orchestration state, not universal domain truth;
- reuse `ActorProfileIntent`, Concepts and existing policies/Actions.

Exit: all proof paths create understandable, queryable current-intent state with no Match/Contract implication.

## Phase 9 — Published Content Library and reference/placement semantics

**Status: remote implementation complete and green on `feat/ideal-v1-09-content-library-placement` at `fdb7c1cbfbe1a81c284c67df79a71b0e5ee3074a` / GitHub Actions `36020336046`.**

Purpose: productize Content as an independent published artifact system usable across Personal, GroupSpace, Admission and future Relationship/Project Contexts.

Deliverables:

- viewer-authorized library of **published** Content only;
- filters by purpose/type: Post, Article, Book/Booklet, Diary/Note, Album, Lesson, Report/Activity, Evidence, Questionnaire and future Blueprint purposes;
- orthogonal semantic filters through Concepts/classification;
- author/context/language/date filters where useful;
- contextual card/page `⋮` actions derived from permission;
- normal Content placement/presentation that may follow current published revision;
- evidence/citation that pins exact published revision + optional block/field/asset/relationship target;
- Content or exact blocks/revisions may be referenced from GroupSpace, Relationship, Timeline, Planner, Contract/Fulfillment evidence without copying the artifact.

No parallel `group_posts`, `personal_articles`, album, diary or evidence tables.

Exit: Alice can publish an Article/Album in its home Context; an actor who independently has source-read authority and target-management authority can present/reference it in an authorized GroupSpace; Bob can consume the placed artifact through that target and cite an exact revision/block as evidence without historical drift. Placement never manufactures source authority or transitive resharing.

## Phase 10 — Relationship + Relationship Context

**Status: remote runtime complete and green on feat/ideal-v1-10-relationship-context at 6cb21465489c14efffc589c41070f46160e029c2 / GitHub Actions 36025406749 (439 tests / 2481 assertions).**

Purpose: represent meaningful ongoing Actor-to-Actor / Actor-to-organization participation outside Group Membership.

Deliverables:

- Relationship;
- explicit participants/roles;
- purpose/Concept;
- lifecycle;
- dedicated Context/access policy;
- links to originating Intent/direct request;
- capability discovery based on relationship purpose, not combinatorial type enums.

Proof: Alice ↔ Bob client/provider; Alice ↔ Carol collaborator/capital relationship.

Exit: direct relationships exist without fake Groups and can host independent Content/Conversation/Planner capabilities later.

## Phase 11 — Conversation + Unified Timeline

**Status: remote implementation complete and green on `feat/ideal-v1-11-conversation-timeline` at `6dcd43a056290730eaab608b6291a96ce8ff4b62` / GitHub Actions `36030315938` — 448 tests / 2533 assertions.**

Purpose: make collaboration understandable while keeping messages non-authoritative.

Deliverables:

- Context-scoped Conversation/messages/replies;
- attachments/reference cards through existing Assets/Content;
- read-only Timeline projection from durable domain events;
- links from timeline entries to authoritative source object/action;
- relationship/admission/group composition.

Exit: a reload reconstructs the same conversation/timeline state; “I agree” text alone changes no authoritative lifecycle.

## Phase 12 — Personal Activity / Planner

**Status: remote implementation complete and green on `feat/ideal-v1-12-personal-activity-planner` at `8d9f5690ba73daf0f73e03d86981d076ade1cd4e` / GitHub Actions `36037941878` — 458 tests / 2615 assertions.**

Purpose: support everyday one-time and recurring activity for personal and collaborative life.

Deliverables:

- Plan;
- ScheduleRule;
- Occurrence;
- Participant;
- actual start/end;
- completion;
- Content/Asset evidence;
- reminders seam;
- list/calendar/today views.

Proof: Bob studies, Alice has an appointment, Bob works selected 08:00–17:00 days.

Exit: one planner supports personal and relationship-sourced activity without becoming Contract authority.

## Phase 13 — Personal Accounting v1

**Status: remote implementation complete and green on `feat/ideal-v1-13-personal-accounting` at `c5a45f7d4178637e322855e71318709610e835b8` / GitHub Actions `36042662030` — 469 tests / 2714 assertions.**

Purpose: give immediate everyday money tracking with rigorous accounting underneath.

Friendly actions:

- Opening balance;
- Add expense;
- Add income;
- Transfer.

Kernel:

- MonetaryUnit;
- Ledger;
- Account;
- JournalEntry;
- JournalLine;
- reversal/correction;
- derived balances and period summaries.

Proof: Bob starts with X cash, buys gloves for W, current balance derives to X−W; daily/week/month/year income/expense/net reports reconcile to immutable entries.

Exit: normal users never need debit/credit terminology for routine entry, while ledger truth remains balanced/immutable.

## Phase 14 — Proposal + Negotiation

**Status: remote runtime implementation complete and green on `feat/ideal-v1-14-proposal-negotiation` at `a27a2538161ff36d123eef1bd0f9d9c153298987` / GitHub Actions `36048778108` — 477 tests / 2799 assertions.**

Purpose: turn a direct request or discovered opportunity into explicit proposed terms.

Deliverables:

- Proposal;
- Negotiation Context;
- parties;
- versioned proposed terms using sealed Content revisions where appropriate;
- Conversation around proposal;
- accept/reject/request-change UI that still does not create Contract until the explicit Contract action.

Proof: Alice proposes Riverside construction collaboration to Bob and Carol.

## Phase 15 — Contract, ContractVersion and explicit acceptance

**Status: remote runtime implementation complete and green on `feat/ideal-v1-15-contract-version-acceptance` at `df63697b3734bc3a8dfe1b70f58655d4b2c9da72` / GitHub Actions `36055829092` — 487 tests / 2903 assertions.**

Purpose: create exact party-specific authoritative terms.

Deliverables:

- Contract;
- immutable ContractVersion;
- exact parties/roles;
- sealed terms Content reference;
- required-party acceptance;
- activation/supersession/effective-time;
- amendments for future behavior without historical mutation.

Proof: Alice/Bob paid-work terms and Alice/Bob/Carol Riverside multi-party terms.

## Phase 16 — Commitment + Fulfillment

**Status: remote runtime implementation complete and green on `feat/ideal-v1-16-commitment-fulfillment` at `67b986cce96f7d6e72db811045a9c1a01c581ddb` / GitHub Actions `36093668972` — 495 tests / 2989 assertions.**

Purpose: distinguish what must happen from what actually happened.

Deliverables:

- Commitment;
- Planner binding/materialization;
- Fulfillment;
- quantity/duration/start/end/status;
- exact Content/Asset evidence;
- accept/reject/request-clarification review;
- dispute/correction lifecycle where required.

Proof: Bob performs and submits one construction workday; Alice reviews it.

## Phase 17 — Financial Obligation + Settlement bridge

**Status: remote implementation complete and green on `feat/ideal-v1-17-financial-obligation-settlement`; runtime `47bae48638345a807623ceb7f1ae44866d09d9e6` / CI `36115933156` — 502 tests / 3065 assertions; documentation/manual `a24c1c3a3a34290c15e8d3d5cb633df22d5bead9` / CI `36116508652` — 502 tests / 3069 assertions.**

Purpose: let authoritative relationship events create financial consequences safely.

Chain:

~~~text
accepted ContractVersion
→ Commitment
→ accepted Fulfillment or other defined economic event
→ Financial Obligation
→ explicit accounting posting Action
→ JournalEntry
→ Settlement / Payment record
→ settlement accounting
~~~

Exit: the system derives scheduled/worked/accepted/earned/paid/outstanding/disputed values and never mutates a magic balance directly.

## Phase 18 — Journey / Relationship / Domain Blueprints

**Status: implementation complete / runtime CI green on `feat/ideal-v1-18-domain-blueprints`; documentation/manual closure and integration gate in progress.** Runtime checkpoint `e4c9cb4cce39a1e0a37bad6ae72e97b6ab5feb77` / CI `36119387963`: **510 tests / 3138 assertions**, Pint 638 changed PHP files, PHPStan/Vite/migrations/operations/backup/security gates green. Detailed contract: `docs/PHASE_18_DOMAIN_BLUEPRINTS.md`.

Purpose: productize proven compositions only after their patterns exist.

Initial compositions:

- Simple Sale;
- Rental;
- Service Job;
- Employment / paid work;
- Construction Partnership;
- Personal activity.

Delivered:

- versioned `DomainBlueprint` / immutable published `DomainBlueprintVersion`;
- normalized terminology, recommended capabilities, Content Blueprint references and guided-entry configuration;
- six idempotent built-in compositions;
- exact Blueprint-version provenance on created Relationship/Plan records and creation events;
- journey-kind enforcement so recipes cannot be applied to the wrong domain surface;
- authenticated **Journeys** catalog;
- guided Relationship creation for the five collaboration recipes;
- guided Planner creation for Personal Activity;
- source-recipe visibility on resulting Relationship/Plan pages;
- tests proving recipes guide but do not create Contract, Commitment, Financial Obligation or Accounting truth.

Blueprints configure terminology, recommended capabilities, Content templates and guided entry; they never replace domain authority, grant permission, or execute arbitrary code.

Exit gate: final feature-branch CI and integration PR must be green; System Manual Chapter 23, phase report/current-state/worksheet synchronization, and exact checkpoint evidence must be committed before merge.

## Phase 19 — Need / Offer Matching

**Status: runtime complete / remote-green on `feat/ideal-v1-19-need-offer-matching`; documentation/manual closure and integration gate in progress.** Runtime checkpoint `532901b4e11d78cb5d848ca4bb6039632c62f3a6` / CI `36124849892`: **517 tests / 3192 assertions**, Pint 645 changed PHP files, PHPStan/Vite/migrations/ops/backup/security gates green. Detailed contract: `docs/PHASE_19_NEED_OFFER_MATCHING.md`.

Delivered:

- derived candidate matching over existing Active ActorProfileIntent records;
- opposite Need/Offer direction and different-Actor enforcement;
- canonical Concept identity matching without silently assuming hierarchy substitutability;
- subject/arrangement compatibility;
- quantity/unit, location/origin/destination, date/time/recurrence and value-range constraints when explicitly available;
- deterministic human-readable explanation dimensions;
- normal Intent visibility policy before candidate disclosure;
- private-Profile identity protection;
- owner-only **Find matches** experience;
- direct selected-candidate revalidation at handoff time;
- exact `originating_intent_id` + `matched_intent_id` Relationship provenance;
- explicit handoff to a **Proposed Relationship**, preserving counterparty consent before Proposal;
- no Match table and no obligation/authority from discovery itself.

Proof: Alice's construction Need finds Bob's compatible Service Offer; Alice's Capital Need finds Carol's compatible Capital Offer. Incompatible/private candidates are excluded, and discovery creates no downstream authority.

Exit gate: System Manual Chapter 24, phase report/current-state/worksheet synchronization, final feature-branch CI and integration PR must be green before Phase 20.

## Phase 20 — Groups / Communities social composition

**Status: runtime complete / remote-green on `feat/ideal-v1-20-group-community-composition`; documentation/manual closure and integration gate in progress.**

Runtime checkpoint `6782bdaeb2a732ec2f9ae8f72c76dc5f311d6f17` / CI `36126321777`: **520 tests / 3219 assertions**, Pint 651 PHP files, PHPStan/Vite/migration/ops/backup/security gates green.

Delivered:

- a dedicated member-facing Group Community route/surface;
- existing Group Show retained as governance/settings;
- visible GroupSpace cards linking the same Conversation, Content, Planner, Timeline and Submission-review kernels;
- active people/role composition from existing Group Membership/role authority;
- policy-filtered member Need/Offer composition;
- policy-filtered published Content composition;
- Context-scoped Plan composition;
- review-authorized Submission composition;
- viewer-participated shared Relationship/project composition;
- no Group-specific copies of those domain records;
- explicit privacy tests proving Group membership is not a cross-domain permission bypass.

Detailed contract: `docs/PHASE_20_GROUP_COMMUNITY_COMPOSITION.md`.

Exit gate: System Manual Chapter 25, closure docs/checkpoint synchronization, final feature-branch CI and integration PR must be green before Phase 21.

## Phase 21 — Home / Today personal operating view

**Status: runtime complete / remote-green on `feat/ideal-v1-21-home-today`; documentation/manual closure and integration gate in progress.**

Runtime checkpoint `d0834e5a72545d558d074deb495fd7009daa96ff` / CI `36128745672`: **524 tests / 3244 assertions**, Pint 660 PHP files, PHPStan/Vite/migration/ops/backup/security gates green.

Delivered:

- Dashboard route upgraded to the derived **Today** operating view;
- policy-filtered PlanOccurrences for the current local day;
- explicit **Waiting on me** actions from Relationship/Proposal/Contract/Fulfillment/Settlement/Submission lifecycle state;
- **Waiting on others** where the current user already acted and a required counterparty is pending;
- active Needs/Offers, Relationships and Group memberships;
- today's actual Personal Accounting income/expense/net grouped by MonetaryUnit;
- recognized receivable/payable and confirmed-paid/outstanding obligation summaries grouped by MonetaryUnit;
- recent authorized activity from existing ContextTimeline sources;
- safe verified-account-without-Actor behavior;
- preservation of office-alpha release boundaries;
- localized Today catalogs for English, Persian, Arabic and Chinese;
- no Home/Today persistence and no inferred unread state.

Detailed contract: `docs/PHASE_21_HOME_TODAY.md`.

Exit gate: System Manual Chapter 26, closure docs/checkpoint synchronization, final feature-branch CI and integration PR must be green before Phase 22.

## Phase 22 — Realtime + Notifications

**Status: complete and integrated into `integration/ideal-v1` at `25a24e0fd0853347d73ec7b262ac274f76f40c6a`.**

Implemented: transactional notification outbox, post-commit queued delivery, durable database inbox/read state, authorized private broadcasting, Reverb/Echo refresh, Planner reminder emission and lifecycle notification projection. Realtime remains transport only; database/domain Actions remain authoritative.

## Publishable Ideal-v1 checkpoint — inserted before Phase 23

**Status: active on `feat/ideal-v1-publishable-hardening`.**

The first public Ideal-v1 is intentionally bounded at the cumulative Phase 1–22 capability set plus release hardening. Before any speculative kernel expansion:

1. close inherited authorization/privacy/UX correctness defects;
2. pass complete CI on the exact hardening candidate;
3. synchronize release gate, operations, current-state and cumulative acceptance documentation;
4. integrate the exact green candidate into `integration/ideal-v1`;
5. require green integration CI and freeze one immutable release-candidate SHA;
6. run the owner-local cumulative Alice/Bob/Carol/Diego 0→100 browser acceptance;
7. regression-test and close every release-blocking browser defect;
8. tag the accepted stable release.

Generic Workflow extraction, Reputation, Recommendations, AI Copilot and broad system-wide polish do **not** delay this first release unless a concrete defect in an already-supported journey proves one is necessary.

See `docs/PUBLISHABLE_V1_RELEASE_GATE.md` and the final section of `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

## Phase 23 — Generic Workflow extraction — post-v1

Extract WorkflowDefinition/State/Transition/Requirement only after Admission plus at least one unrelated proven domain demonstrate the same reusable pattern.

Do not make Workflow a universal interpreter.

## Phase 24 — Reputation / verified history — post-v1

Derive context-sensitive trust/history from real evidence such as accepted Fulfillment, Evaluations, verified skills and completed relationships.

No universal opaque score.

## Phase 25 — Discovery / recommendations — post-v1

Recommend people, Groups, Content, projects and opportunities from explicit authorized data with human-readable reasons and user control.

Avoid addictive opaque-feed optimization.

## Phase 26 — AI Copilot — post-v1

Reintroduce AI only after deterministic UX/domain Actions are excellent.

AI may:

- interpret natural language;
- prefill wizard drafts;
- prepare Content revisions;
- prepare activity/expense records;
- prepare Proposal/Contract revisions.

AI may not silently submit, approve, accept, publish, finalize, pay or post accounting truth.

Provider output remains untrusted and must pass the same server-side validation/policy/Action as human UI.

## Phase 27 — System-wide UX / accessibility / localization polish — post-v1 continuation

Unify:

- responsive/mobile/RTL;
- navigation/progressive disclosure;
- empty/loading/error states;
- accessible keyboard/focus behavior;
- consistent cards/tables/`⋮` menus;
- canonical English manual/UI copy;
- reviewed Persian translation workflow;
- Arabic/Simplified Chinese after the translation process is proven.

## Phase 28 — Production hardening and real-domain pilots

Pilot the complete system with:

- Riverside housing/construction story;
- paid-work relationship;
- simple sale/rental;
- personal activity/accounting;
- Group/community collaboration.

Hardening includes security assessment, privacy/export/deletion/retention, moderation, performance/index review, queue failure drills, backup/restore, observability, deployment/rollback and incident runbook.

## Phase 29 — Integrated release candidate + deferred owner acceptance

Freeze one immutable candidate from `integration/ideal-v1`.

Generate the completed `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

Owner validates milestone checkpoints and then the full Alice/Bob/Carol/Diego 0→100 browser story on the continuing local database.

Defects become regression-tested correction commits; do not rewrite historical checkpoint SHAs.

## Phase 30 — Stable release and operating loop

After the final human gate:

- release branch;
- immutable release tag;
- deployment artifact;
- migration/backup/rollback confirmation;
- release notes + known limitations;
- support/incident ownership;
- feedback → roadmap loop.

## Selective assembly progression rule

For the current first-publication reconciliation, `docs/SELECTIVE_ASSEMBLY_ROADMAP.md` supersedes the older remote-only progression rule.

A module may be implemented and validated remotely, but it is **not admitted into `codex/ideal-v1-selective-assembly` until the owner has browser-tested and explicitly accepted the exact review head**. Browser findings are regression-tested and corrected on the review/correction branch before merge. Only after merge + post-merge CI + checkpoint documentation may implementation of the next dependent module begin.

The final cumulative 0→100 release acceptance still runs after all module-level gates; module acceptance reduces integration ambiguity but does not replace final release validation.
