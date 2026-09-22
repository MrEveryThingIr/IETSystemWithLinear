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

**Status: runtime technically complete / remote-CI green; final owner-local/browser/mobile/RTL acceptance pending.** Detailed contract: `docs/PHASE_07_SUBMISSION_EVALUATION.md`. Runtime candidate: `35236f7af4ab9168467383b867cd698f2f30755c` (GitHub Actions run `35772393444`: 393 tests / 2086 assertions; changed-file Pint 307 files; PHPStan/Vite/ops/backup/security green; Phase 7 migrations rollback/reapply green).

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

Automated/runtime gate satisfied on `35236f7af4ab9168467383b867cd698f2f30755c`: both proof cases work without abusing annotations or creating separate form engines; submitted evidence remains historically exact after newer Content/interaction versions; pre-Membership Admission isolation is preserved; and the application components can later be composed into Conversation without changing their domain semantics. Final owner local/existing-database plus browser/mobile/RTL acceptance remains required before Phase 7 is formally closed or Phase 8 runtime work begins.

## Phase 8 — Admission v2: contextual onboarding

### Purpose

Upgrade the current Admission flow to use Profile requirements, Admission Context and Submissions.

### Deliverables

- configurable admission mode;
- requested profile facts/assertions;
- reuse/share-existing-profile flow;
- admission-specific questionnaire;
- document/evidence requirements;
- agreement requirements;
- shared candidate/reviewer Conversation inside Admission Context;
- optional reviewer-internal Conversation with independent audience authorization;
- read-only system timeline generated from durable Admission/domain events;
- reviewer clarification/evidence requests expressed through conversation plus structured requirements rather than multiplying lifecycle states for every question;
- explicit authoritative actions for submission, review, approval/rejection, exact Agreement-version acceptance, and Membership finalization;
- proposed Group Agreement revision references where discussion reveals a Group-wide rule change, without mutating the active version;
- candidate-specific proposed terms routed toward the future negotiated Agreement/Contract model rather than Group-wide Agreement mutation;
- initial role/context provisioning policy;
- audit trail.

### Exit gate

Invitation onboarding can represent simple instant-ish membership and reviewed application flows without granting premature Membership; a reviewed application can carry persistent candidate/reviewer collaboration and structured evidence while all authoritative state remains explicit and auditable.

## Phase 9 — Real-time collaboration infrastructure

### Purpose

Make collaborative experiences live without making WebSockets authoritative.

### Deliverables

- domain event/outbox pattern;
- post-commit dispatch;
- queue-backed broadcasting;
- authorized channels;
- notifications;
- GroupSpace and Admission-context chat/reply live delivery over the same authorized real-time infrastructure;
- system conversation entries derived from committed domain events rather than messages triggering hidden state changes;
- annotation/reaction live delivery;
- submission/workflow event delivery;
- reconnect/reload correctness;
- idempotent clients where necessary.

### Exit gate

Two browser sessions receive authorized updates live, and a reload reconstructs identical authoritative state from DB.

## Phase 10 — Generic Workflow Kernel

### Purpose

Extract reusable state/transition behavior only where proven.

### Deliverables

- WorkflowDefinition/Version;
- State;
- Transition;
- Requirement;
- role/permission authorization;
- transition evidence/history;
- integration adapters.

### Proof cases

- Admission;
- a second unrelated review/approval domain.

### Exit gate

The engine supports both without weakening their domain invariants.

## Phase 11 — Planner

### Purpose

Unify personal and collaborative temporal planning.

### Deliverables

- Plan;
- ScheduleRule;
- Occurrence;
- Participant;
- Completion;
- Evidence;
- recurrence;
- timezone correctness;
- reminders/notifications;
- calendar/list views.

### Proof cases

- nightly chess study;
- weekly class;
- construction shift.

### Exit gate

One planner handles all three with purpose-specific UX.

## Phase 12 — Group Blueprints and Domain Packs v1

### Purpose

Make the generic kernels usable as focused products.

### Deliverables

- versioned GroupBlueprint;
- roles/permissions provisioning;
- Spaces/Contexts;
- Content Blueprints;
- onboarding requirements;
- planner templates;
- explicit upgrade semantics.

### First Domain Packs

- Learning;
- Project / Work;
- Personal.

### Proof cases

- Chess Learning Group;
- Construction/Project Group.

### Exit gate

A user can create each focused environment without understanding generic infrastructure.

## Phase 13 — Need / Offer / Matching

### Purpose

Model supply and demand explicitly.

### Deliverables

- Need;
- Offer;
- semantic Concept requirements;
- quantity/unit constraints;
- time/location/context constraints;
- lifecycle;
- matching service;
- explanations for matches;
- privacy/access.

### Proof cases

- construction labor need/offer;
- tourism/restaurant service need/offer.

### Exit gate

Matching is useful but creates no obligation until explicit Proposal/Agreement.

## Phase 14 — Negotiation, Agreement, Commitment and Fulfillment

### Purpose

Turn matched intent into explicit obligations and evidence.

### Deliverables

- Proposal;
- Negotiation Context;
- negotiated Agreement/Contract distinct from Group Agreement;
- party model and explicit required-party acceptance;
- immutable proposed/accepted Contract versions with activation/supersession semantics;
- versioned terms referencing sealed Content;
- negotiation Conversation as collaborative evidence, with explicit domain Actions as the only source of acceptance/activation truth;
- Commitment;
- partial/full Fulfillment;
- evidence;
- disputes/corrections as explicit lifecycle where required;
- financial-obligation handoff interface.

### Exit gate

A service relationship can progress from proposal to verified fulfillment with full provenance.

## Phase 15 — Production Accounting Kernel

Architecture: `docs/FINANCIAL_ARCHITECTURE.md`.

### Purpose

Record financial truth safely.

### Deliverables

- MonetaryUnit;
- ExchangeRate;
- Ledger;
- Account/chart structure;
- JournalEntry;
- JournalLine;
- reversal;
- idempotent posting;
- balance cache/snapshot;
- source-domain accounting actions;
- reporting foundation.

### Proof cases

- personal expense;
- worker payroll accrual/payment;
- restaurant payable/payment.

### Exit gate

All proof cases produce correct immutable balanced accounting and survive concurrency/idempotency tests.

## Phase 16 — Financial Laboratory

### Purpose

Experiment safely with internal value systems.

### Deliverables

- FinancialInstrument;
- immutable instrument versions;
- issuance/transfer/burn;
- versioned valuation policy;
- valuation snapshots;
- reserve/distribution policy;
- simulated redemption;
- OperationalCycle experiment model;
- strict separation from real payment infrastructure.

### Exit gate

A Group can run a repeatable instrument experiment without creating real-world cash obligations or corrupting the production ledger.

## Phase 17 — Controlled external-money integration

### Purpose

Support approved real payment/payout flows without turning arbitrary Group instruments into money.

### Preconditions

- Accounting Kernel stable;
- operational monitoring/backups mature;
- selected provider;
- jurisdiction/use-case compliance review;
- explicit product approval.

### Deliverables

- PaymentIntent;
- ProviderAttempt;
- PayoutRequest;
- webhook verification/idempotency;
- reconciliation;
- accounting posting;
- limits/holds;
- fraud/abuse controls;
- approved redemption policy where legally/operationally valid.

### Exit gate

External money movement reconciles exactly to provider evidence and the accounting ledger.

## Phase 18 — Discovery and personalization

### Purpose

Use explicit semantic/profile data to improve usefulness.

### Deliverables

- semantic search;
- Content/Group/Blueprint discovery;
- Need/Offer discovery;
- explainable recommendation reasons;
- user controls;
- privacy-preserving signals;
- feedback loops.

### Rule

Do not infer sensitive facts merely to improve recommendations.

### Exit gate

Recommendations can explain why an item appears and users can control relevant profile/discovery inputs.

## Phase 19 — Production hardening and real-domain pilots

### Purpose

Validate the whole platform under real usage.

### Pilot domains

At minimum:

- Learning;
- Project/Construction;
- Personal workspace.

### Hardening

- security assessment;
- authorization audit;
- privacy/export/deletion;
- retention;
- moderation/reporting;
- accessibility audit;
- mobile/browser matrix;
- performance/load tests;
- DB/index review;
- queue failure drills;
- backup/restore drill;
- incident runbook;
- observability dashboards;
- deployment rollback;
- dependency vulnerability process.

### Exit gate

Each pilot runs real workflows without bypassing kernels, and critical operational/security issues are resolved.

## Phase 20 — Production release and operating loop

### Purpose

Establish a supportable production release rather than declaring the project "finished forever."

### Release gate

- all Phase 19 critical gates pass;
- documented deployment;
- tested rollback;
- tested restore;
- alerting;
- support/incident ownership;
- migration plan;
- release notes;
- version tag;
- known limitations documented;
- privacy/security obligations addressed for enabled features;
- no experimental financial capability is exposed as production redeemable value unless Phase 17 specifically approved it.

### Post-release loop

~~~text
Observe
→ measure
→ collect user friction
→ classify problem
→ improve Blueprint/UX first
→ change kernel only when evidence requires it
→ test
→ release
~~~

The platform is never "complete" in the sense of requiring no future evolution. Phase 20 means the defined product scope is reliably operable in production.

## What not to do during this roadmap

- do not implement all phases in one agent session;
- do not create speculative tables for distant phases;
- do not rewrite stable kernels because a future name may be cleaner;
- do not use seeders as product architecture;
- do not introduce a universal JSON entity table;
- do not turn all Content into domain truth;
- do not turn all domain objects into Content;
- do not build recommendation AI before trustworthy semantic/profile data exists;
- do not connect laboratory tokens to bank payouts before the controlled-money phase;
- do not claim production readiness from test count alone.

## Agent stop rule

At the completion of each phase:

1. write/update the phase report;
2. report exact Git state;
3. report validation actually run;
4. identify unresolved issues;
5. stop;
6. wait for human/architecture review before beginning the next phase.
