# Phase 7 — Submission / Response / Evaluation

## Status

**Active** on `feat/phase-07-submission-evaluation`. Phase **7A is complete and human/local accepted**; Phases **7B, 7C and 7D are technically complete**; Phase **7E — proof and closure — is next**.

Starting baseline:

`0d98dfe3c99e79dbdbc72dfc9b6f3fbe50a7f533`

That baseline includes the post-Phase-6 invitation locale-return fix and is green on GitHub Actions run `35749114796`: **366 tests / 1945 assertions**, PHPStan clean, changed-file Pint **262 files**, Vite/build/operations/security gates green.

The human owner authorized Phase 7 kickoff on 2026-09-22 and explicitly confirmed the product direction that structured interactions must support a later **conversation-first Admission experience** without making chat messages authoritative domain state.

## Why Phase 7 exists

Annotations, comments and chat are good for human discussion. They are not sufficient for structured applications, exams, questionnaires, requested evidence, attestations or evaluations.

Phase 7 introduces one reusable structured-interaction kernel that can later appear:

- as a normal standalone interaction attached to Content;
- inside a GroupSpace learning/work experience;
- inside an Admission Context;
- as a structured card embedded in a future Conversation;
- inside later Workflow/domain-pack experiences.

The kernel must be generic internally while remaining purpose-specific in user-facing language.

## Product direction: conversation-ready, not chat-owned

Phase 8 will make Admission collaboration conversation-first. Phase 9 will make collaboration real-time.

Phase 7 must prepare for that UX without implementing either phase early.

Target composition:

~~~text
Conversation
├── ordinary human messages
├── Requirement card
├── Submission / Response card
├── Evidence / Asset card
├── Agreement-version card
├── Evaluation / feedback card
└── explicit domain-action card
~~~

The same structured interaction must also work without Conversation:

~~~text
Published Content
→ interaction prompt
→ draft Submission
→ Responses / evidence
→ explicit Submit
→ reviewer Evaluation
~~~

Conversation may explain, request, propose and discuss. It does not itself submit, approve, accept, finalize Membership, activate a Contract or create another authoritative fact.

## Core model

### InteractionDefinition

`InteractionDefinition` is the stable identity of a structured interaction contract.

It is not a form page and it is not Content itself.

Rules:

- stable UUID identity;
- belongs to an authorized Context;
- may be associated with a Content identity;
- carries lifecycle metadata such as draft/active/retired without rewriting historical versions;
- ordinary users should normally encounter purpose-specific labels such as **Application**, **Exam**, **Questionnaire**, **Evidence request**, or **Assignment**, not the generic internal name.

### InteractionDefinitionVersion

Versions are immutable after activation/use.

A version defines trusted structured data such as:

- title/instructions;
- ordered item definitions with stable keys;
- response type from a trusted registry;
- required/optional state;
- safe validation constraints;
- selectable options where applicable;
- attempt/withdrawal policy where applicable;
- evaluation/rubric hints where applicable;
- exact optional Content-revision binding;
- canonical configuration hash.

Changing the structure creates a new version. Existing Submissions remain pinned to the exact version they used.

Phase 7 must not allow database-authored PHP, Blade, JavaScript, SQL or arbitrary executable validation.

### Initial response-type registry

Keep the first registry intentionally small and proven:

- short text;
- long text;
- boolean / confirmation;
- number;
- date;
- single choice;
- multiple choice;
- Asset/document evidence;
- exact Content evidence reference.

Additional types require a real proof case rather than speculative catalog growth.

### Submission

A `Submission` is one Actor's structured attempt/application/response instance.

It binds exactly to:

- Context;
- submitter Actor;
- `InteractionDefinitionVersion`;
- associated Content identity where present;
- exact Content revision where the interaction was presented against.

Lifecycle for Phase 7:

~~~text
draft → submitted → withdrawn
~~~

Rules:

- draft Responses may be edited;
- submit is an explicit authorized Action;
- submitted Responses are immutable evidence;
- withdrawal never erases the submitted snapshot;
- a retry/resubmission is a new Submission/attempt unless an explicit later policy introduces a different audited mechanism;
- submission evidence must not drift when Content or an InteractionDefinition later changes.

At submit time, persist enough canonical evidence to prove exactly what was submitted against what version. The implementation should include an integrity hash over the exact definition/content binding plus normalized Responses and referenced evidence identities.

### Response

A `Response` answers one stable item key in the exact InteractionDefinitionVersion.

Rules:

- unique within a Submission/item unless the response type explicitly models a collection;
- value is normalized according to its trusted response type;
- response storage must not become an arbitrary executable JSON entity system;
- file/media responses reference reusable authorized Assets;
- Content proof references the existing immutable `ContentEvidenceReference` seam where appropriate;
- every Response receives stable addressable identity so later annotations/conversation/evaluation can target it safely.

### Evaluation

`Evaluation` records an authorized review of an exact submitted Submission.

Rules:

- Evaluation is distinct from Conversation feedback;
- Evaluation is distinct from Admission approval/rejection, Membership, Workflow transitions or Contract acceptance;
- draft evaluator work may be editable;
- finalized Evaluation is immutable evidence;
- narrative feedback and structured rubric/result data may coexist;
- the exact evaluated Submission and version binding must remain recoverable.

Phase 7 should support useful evaluation without prematurely inventing the generic Workflow engine from Phase 10.

## Authorization

Do not piggyback authoritative Submission permissions onto unrelated Content annotation permissions merely because both occur near Content.

Phase 7 should introduce explicit Context-aware authorization seams for at least:

- view interaction;
- start/create Submission;
- update own draft Submission;
- submit/withdraw own Submission when policy allows;
- view a Submission;
- evaluate/review a Submission;
- manage InteractionDefinitions.

Context remains the collaboration/visibility boundary.

Important proofs:

- a GroupSpace participant can submit where authorized;
- an Admission candidate can submit inside the Admission Context before Membership;
- the Admission candidate still cannot use ordinary Membership-gated Group access;
- reviewer access does not leak across Groups/Contexts.

## Assets and evidence

Do not create a second upload/blob system.

Phase 7 reuses the existing private `Asset` pipeline:

- Context binding;
- uploader provenance;
- immutable stored-file identity;
- MIME/size checks;
- scan/processing/readiness lifecycle;
- private authorized retrieval;
- SHA-256 provenance.

If current Asset creation is too coupled to Content/annotation Actions, Phase 7 may extract a reusable Context-Asset Action/service while preserving all existing Content/Profile behavior.

A Submission/Response references Assets through explicit relationships. The Asset is not owned by a public message and must not become publicly reachable merely because it appears in an interaction.

## Relationship to Content

Content remains the human-facing artifact.

Examples:

- an Exam Content explains the exam; its InteractionDefinition defines the answer contract;
- a Job/Application Content explains the opportunity; its InteractionDefinition defines requested application Responses;
- a Questionnaire Content supplies the authored presentation; Phase 7 supplies the structured respondent data.

Exact historical truth requires both:

~~~text
Content revision
+ InteractionDefinitionVersion
+ Submission/Responses
~~~

A later Content publication or InteractionDefinition version must not silently reinterpret an earlier Submission.

## Relationship to annotations

Annotations remain conversational/contextual interaction around Content.

Do not encode Submission answers as annotations.

Do not encode evaluator results as annotation kinds.

Later, annotations may target stable Submission/Response/Evaluation identities where useful.

## Relationship to Admission

Phase 7 does **not** redesign the Admission UX or replace its current lifecycle.

It supplies the structured substrate Phase 8 will compose into the conversation-first Admission workspace:

- requirements/questions;
- requested documents/evidence;
- candidate Responses;
- evaluator feedback/results.

The existing note-heavy Admission flow is intentionally left compatible until Phase 8 migrates the experience.

## Relationship to Conversation and real-time

Phase 7 does not introduce a generic Conversation table and does not generalize the current GroupSpace chat prematurely.

Phase 8 owns Admission Conversation composition.

Phase 9 owns real-time/outbox/broadcast infrastructure.

Phase 7 UI/application components should nevertheless be embeddable: they must not assume that a structured interaction always owns an entire page or that navigation itself is the domain lifecycle.

## Pre-implementation audit

The Phase 7 kickoff audit found:

- `ContextPolicy` already proves Personal, GroupSpace and Admission authorization, including pre-Membership Admission collaboration;
- Content annotations already support rich typed discussion, replies, stable anchors and private Assets, but are intentionally Content-revision interaction rather than structured Submissions;
- `Asset` already provides Context binding, immutable provenance, media/security readiness and reusable relationships;
- immutable `ContentEvidenceReference` gives Phase 7 a precise Content-proof seam;
- current Admission `Show` + `ManageAdmission` still use note-bearing lifecycle transitions; Phase 8, not Phase 7, owns the conversation-first replacement;
- current `GroupSpaceMessage` / `SpaceChat` proves chat UX but is GroupSpace-specific and must not be promoted into the generic Conversation architecture during Phase 7.

Therefore Phase 7 should add a new structured-interaction domain while reusing Context/Asset/Content evidence, not mutate annotations/chat into forms.

## Milestones

### 7A — versioned interaction kernel

**Status: complete and human/local accepted.** Runtime candidate `ceeb85b63c92385a0b714b5d5e9116dfe9e94932`; GitHub Actions run `35756220221` is green: **372 tests / 1964 assertions**, changed-file Pint **272 files**, PHPStan clean, Vite/build/operations/backup/security gates green. On synchronized `bd4ca3a`, the human owner applied the migration to the existing database, passed the focused kernel suite (**6 tests / 19 assertions**), PHPStan **266/266**, dirty-only Pint, `git diff --check`, and a clean tree. Invitation/registration/Admission-resume browser regression remained correct and intentionally showed no new product behavior because 7A is domain infrastructure, not the Phase 8 Admission UX.

Delivered:

- additive migration `2026_09_22_200000_create_interaction_definition_kernel.php`;
- stable `InteractionDefinition` UUID identity and guarded lifecycle;
- immutable `InteractionDefinitionVersion` after activation;
- trusted response-type registry for text, boolean, number, date, choice, Asset and Content-evidence responses;
- bounded/whitelisted version configuration with no executable dynamic code;
- canonical configuration hashing;
- optional exact sealed Content-revision binding;
- explicit activation Action with transactional locking/authorization;
- Context-aware submit/review/manage interaction authorization seams;
- factories and focused lifecycle/security/Admission-context tests.

Original 7A deliverables:

- InteractionDefinition stable identity;
- immutable InteractionDefinitionVersion;
- trusted response-type registry;
- canonical version hashing;
- Context/content binding;
- active-version lifecycle;
- authorization foundation;
- migrations/models/factories;
- lifecycle/versioning tests.

### 7B — Submission / Response / evidence

**Status: technically complete on `7761a2f5a57fd06b8df4fa7e97397cd9f9a2a2ab`.** GitHub Actions run `35766736605` is green: **379 tests / 2000 assertions**, changed-file Pint **284 files**, PHPStan clean, Vite/build/operations/backup/security gates green.

Delivered:

- additive `submissions` + `submission_responses` migration;
- exact immutable InteractionDefinitionVersion and optional Content-revision binding per Submission attempt;
- idempotent draft start with max-attempt enforcement;
- normalized scalar Responses for text/boolean/number/date/single/multiple choice;
- reusable Context Asset evidence through the existing private Asset/security-processing pipeline;
- authorized immutable Content-evidence references, including deliberate cross-Context evidence when the submitter may view the source;
- explicit submit and withdraw Actions;
- canonical schema-versioned submission evidence manifest plus SHA-256 integrity hash;
- immutable submitted Responses and durable withdrawn evidence;
- candidate-owned Admission drafts hidden from reviewers until submit;
- reviewer visibility after submit without granting candidate Membership or GroupSpace authority;
- focused tests for attempt isolation, required answers, Asset provenance, cross-Context evidence, Admission isolation and withdrawal preservation.

Original 7B deliverables:

- atomic draft creation;
- normalized Responses;
- reusable Asset/evidence attachments;
- explicit submit/withdraw Actions;
- immutable submitted snapshot/integrity evidence;
- attempt isolation;
- cross-Context authorization tests.

### 7C — Evaluation

**Status: technically complete on `9e0338994f755f81c59ed6dfe9e5e96ad0afaa8f`.** GitHub Actions run `35767588253` is green: **384 tests / 2025 assertions**, changed-file Pint **293 files**, PHPStan clean, Vite/build/operations/backup/security gates green.

Delivered:

- additive Evaluation evidence table bound to exact Submission and evaluator Actor;
- draft/finalized Evaluation lifecycle with finalized immutability;
- optional immutable rubric definitions on InteractionDefinitionVersion;
- bounded overall score, criterion scores and narrative feedback;
- private evaluator drafts and finalized submitter/reviewer visibility;
- canonical schema-versioned Evaluation evidence plus SHA-256 integrity hash bound to the exact Submission evidence hash;
- idempotent evaluator finalization;
- explicit self-evaluation and outsider rejection;
- `mode=none` support for interactions that must not be evaluated;
- proof that Evaluation finalization does not change Admission lifecycle or create Membership.

Original 7C deliverables:

- reviewer/evaluator authorization;
- draft/finalized Evaluation;
- structured + narrative feedback;
- immutable finalized evaluation evidence;
- no hidden Admission/Workflow transition side effects.

### 7D — productized interaction experience

**Status: technically complete on `d828dc43c9ef469a3532bfee9b6274d5dceb5f24`.** GitHub Actions run `35771215549` is green: **390 tests / 2052 assertions**, changed-file Pint **305 files**, PHPStan clean, Vite/build/operations/backup/security gates green.

Delivered:

- reusable purpose-specific Submission Card embedded in the unified exact-revision Content Reader;
- draft start/save/resume, explicit submit, withdrawal and later-attempt UX backed only by existing Phase 7 Actions;
- scalar, choice, Asset and immutable Content-evidence response inputs;
- protected same-Context Submission Asset download/stream authorization;
- reviewer discovery from the Reader plus Context-level submitted/withdrawn review queue;
- reviewer detail with exact sealed Submission evidence, Evaluation draft/save/finalize and finalized feedback display;
- reviewer queue intentionally excludes candidate drafts;
- Evaluation UX proven not to approve Admission or create Membership;
- exact active Content-revision matching so later publications do not silently reuse an old interaction contract;
- localized product language in English, Persian, Arabic and Simplified Chinese with responsive/RTL-ready layouts;
- focused browser-level Livewire/HTTP coverage for Reader discovery, submit flow, draft privacy, reviewer evaluation, Asset authorization and Persian rendering.

Original 7D deliverables:

- purpose-specific interaction rendering from published Content;
- draft/resume/submit UX;
- reviewer queue/detail UX sufficient for the two proof cases;
- components that can later render as Conversation cards without domain redesign;
- mobile/RTL/accessibility behavior;
- Questionnaire-shell structured-response integration where appropriate.

### 7E — proof and closure

**Status: next.**

Deliver:

- school exam proof;
- employment application proof;
- Admission-Context pre-Membership structured-response proof without Admission-v2 UX rewrite;
- regression coverage for Content/annotations/Assets/Admission;
- full PHPUnit/PHPStan/Pint/Vite/ops/security gates;
- migration/rollback proof;
- implementation report;
- human browser/mobile/RTL acceptance.

## Proof case A — school exam

A teacher/authorized manager publishes an exam-like Content interaction.

A learner:

- sees the exact published Content edition;
- starts a draft Submission;
- answers multiple response types;
- submits explicitly;
- cannot silently alter the submitted answers afterwards.

An authorized evaluator:

- views the exact submitted snapshot;
- evaluates it;
- finalizes feedback/result;
- cannot mutate the learner's answers.

A later exam/definition revision does not reinterpret the old attempt.

## Proof case B — employment application

An application-oriented Content interaction requests structured answers and evidence.

A candidate:

- can respond inside an authorized Context;
- can attach a CV/document through the reusable Asset pipeline;
- can cite exact Content evidence where appropriate;
- submits one immutable application attempt.

An authorized reviewer can evaluate that exact attempt without granting unrelated Group authority.

The same kernel must be suitable for Phase 8 Admission composition; Phase 7 must not create a separate `AdmissionApplication` form engine.

## UX requirements

Prefer purpose language:

~~~text
Apply
Answer questions
Upload requested evidence
Save draft
Submit application
Take exam
Review submission
Give feedback
~~~

Do not expose generic internal terms unnecessarily.

Progressive disclosure applies here too: common interactions should remain simple even though the kernel supports evidence, evaluation and multiple response types.

## Security and integrity

Phase 7 must explicitly test:

- Context and cross-Group isolation;
- candidate versus reviewer boundaries;
- ID/UUID possession never grants access;
- submitted Response immutability;
- finalized Evaluation immutability;
- definition-version immutability;
- exact Content-revision binding;
- external/file authorization through reusable Asset policy;
- no executable dynamic definitions;
- concurrency/idempotency around submit/withdraw/finalize evaluation;
- no duplicate successful finalization under retries;
- bounded input sizes and validated response types.

## Migration rules

- additive migrations only;
- no destructive reset;
- SQLite-compatible rollback;
- preserve all existing Content, Annotation, Asset, Admission and Agreement data;
- do not mass-rename `SpaceContent*` compatibility substrate in this phase;
- historical submitted/finalized evidence must remain readable after future versions.

## Explicitly excluded

Phase 7 does not implement:

- generic Conversation;
- Admission v2 conversation-first workspace;
- reviewer-internal chat;
- WebSockets/realtime/outbox broadcasting;
- generic Workflow engine;
- Admission lifecycle redesign;
- automatic Approval/Membership from Evaluation;
- negotiated Contract/Agreement;
- commitments/fulfillment;
- reputation scoring;
- Planner;
- Need/Offer/matching;
- arbitrary executable form logic;
- a second Asset storage pipeline.

## Stop conditions

Stop for architecture review before changes that would:

- make chat/message wording authoritative;
- bypass Context authorization or grant pre-Membership Group access;
- require a generic Workflow engine to make Phase 7 function;
- mutate sealed Content or submitted evidence in place;
- introduce a separate Admission-only form/response engine;
- duplicate the Asset pipeline;
- turn dynamic definitions into executable code;
- pull Phase 8, 9 or 14 domain behavior into this phase.

## Exit gate

Phase 7 closes only when:

1. one versioned InteractionDefinition kernel supports both proof cases;
2. Submissions bind exact InteractionDefinitionVersion and Content revision where applicable;
3. draft Responses are editable but submitted Responses are immutable evidence;
4. reusable private Assets/evidence work without a second upload system;
5. evaluator authorization and finalized Evaluation evidence are explicit;
6. Evaluation does not secretly perform Admission/Workflow/domain transitions;
7. GroupSpace and Admission-Context authorization boundaries are proven;
8. old Submissions remain historically correct after newer Content/interaction versions;
9. common UX is purpose-specific and components are conversation-embeddable;
10. school exam and employment application work without abusing annotations or creating separate form engines;
11. automated validation is green;
12. browser/mobile/RTL behavior is accepted by the human owner.

At closure, write `Development-CodexReports/phase-07-submission-evaluation-report.md` with exact implementation, migration, test, security, browser and Git evidence.
