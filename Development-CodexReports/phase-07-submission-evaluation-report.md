# Phase 7 — Submission / Response / Evaluation implementation report

## Status

**Runtime technically complete / remote-CI green; final owner-local/browser/mobile/RTL acceptance pending.**

Branch:

`feat/phase-07-submission-evaluation`

Frozen runtime candidate:

`35236f7af4ab9168467383b867cd698f2f30755c`

GitHub Actions run:

`35772393444`

Remote validation on that exact runtime candidate:

- PHPUnit: **393 passed / 2086 assertions**;
- changed-file Pint: **307 files passed**;
- PHPStan: **no errors**;
- Vite production build: passed;
- npm audit: **0 vulnerabilities**;
- Composer security audit: clean;
- scheduler and database-queue smoke: passed;
- SQLite backup → restore smoke: passed;
- Phase 7 migrations `200000`, `210000`, `220000` explicitly rolled back and reapplied successfully before the test suite.

Phase 7 should not be marked human-accepted until the owner runs the local/existing-database and browser/mobile/RTL gate.

## Purpose delivered

Phase 7 adds one reusable structured-interaction substrate for cases that ordinary comments, annotations and chat cannot represent correctly.

The implemented composition is:

~~~text
Content / Context
→ immutable InteractionDefinitionVersion
→ Submission attempt
→ Responses / Assets / exact Content evidence
→ explicit submit
→ optional explicit Evaluation
~~~

The same components are deliberately suitable for later rendering inside Conversation, but Conversation remains collaboration rather than authority.

Phase 7 does not implement Admission v2 Conversation or realtime transport.

## Phase 7A — versioned interaction definition kernel

Delivered:

- stable `InteractionDefinition` UUID identity;
- immutable activated `InteractionDefinitionVersion`;
- trusted bounded response-type registry:
  - short text;
  - long text;
  - boolean;
  - number;
  - date;
  - single choice;
  - multiple choice;
  - Asset evidence;
  - immutable Content evidence;
- whitelisted configuration rather than executable database-authored logic;
- canonical version hashing;
- exact optional sealed Content-revision binding;
- transactional activation Action;
- Context-aware authorization seams for submission/review/management;
- additive migration `2026_09_22_200000_create_interaction_definition_kernel.php`.

Human/local acceptance of 7A was already recorded on 2026-09-22 after the owner applied the migration to the existing database and confirmed the existing invitation/registration/Admission journey remained unchanged as intended.

## Phase 7B — Submission / Response / evidence

Delivered:

- stable `Submission` UUID identity;
- one attempt bound to one exact immutable Interaction Definition version;
- exact optional Content-revision binding copied into the Submission;
- lifecycle:
  - draft;
  - submitted;
  - withdrawn;
- idempotent draft start;
- max-attempt enforcement;
- normalized scalar/choice responses;
- stable `SubmissionResponse` identities;
- draft Responses editable/removable;
- submitted Responses immutable;
- same-Context reusable private Assets for uploaded evidence;
- authorized cross-Context immutable `ContentEvidenceReference` support;
- reusable `CreateContextAsset` Action rather than a second file-storage pipeline;
- explicit submit and withdraw Actions;
- canonical sealed Submission evidence manifest;
- SHA-256 evidence hash;
- private candidate draft behavior before submit;
- additive migration `2026_09_22_210000_create_submission_response_kernel.php`.

Withdrawal preserves the submitted evidence rather than erasing history.

## Phase 7C — Evaluation

Delivered:

- stable `Evaluation` UUID identity;
- evaluator authorization through Context review authority;
- draft Evaluation;
- normalized bounded score;
- versioned rubric criteria from the exact Interaction Definition version;
- criterion scores and narrative feedback;
- explicit finalize Action;
- immutable finalized Evaluation evidence;
- canonical Evaluation evidence manifest and SHA-256 hash;
- submitter visibility of finalized feedback;
- reviewer draft privacy;
- no Admission approval/rejection, Membership creation, Workflow transition or Contract acceptance side effect;
- additive migration `2026_09_22_220000_create_evaluations.php`.

## Phase 7D — productized interaction experience

Delivered:

- reusable Livewire Submission Card;
- exact-revision interaction discovery inside the unified Context Content Reader;
- purpose-specific wording for:
  - Application;
  - Exam;
  - Questionnaire;
  - Evidence request;
  - Assignment;
- start / save draft / resume / submit / withdraw experience;
- scalar, choice, file and exact Content-evidence inputs;
- protected Submission Asset delivery;
- reviewer discovery from the Reader;
- Context-level review queue;
- reviewer detail surface;
- Evaluation draft/save/finalize UX;
- finalized Evaluation feedback shown to the submitter;
- English, Persian, Arabic and Simplified Chinese copy;
- responsive/RTL-ready layouts;
- focused Livewire/HTTP coverage.

The UI calls the Phase 7 Actions; it does not recreate lifecycle rules in the view layer.

## Phase 7E — proof and closure

Delivered:

### School exam proof

Opt-in local fixture:

**Phase 7 — Laravel Fundamentals Exam**

The proof demonstrates:

- GroupSpace Context;
- active learner Membership;
- exact published questionnaire Content edition;
- exact Interaction Definition version;
- multiple response types;
- private draft;
- explicit submit;
- immutable Submission evidence;
- authorized evaluator;
- manual scoring/rubric feedback;
- immutable finalized Evaluation.

The automated closure test then:

1. submits and evaluates version 1;
2. publishes a newer Content edition;
3. activates Interaction version 2;
4. proves the original Submission remains pinned to version 1 and the old Content revision;
5. proves its canonical evidence/hash remain unchanged;
6. proves a new attempt binds the new version/revision.

This directly proves that newer Content/interaction versions do not reinterpret old attempts.

### Employment application proof

Opt-in local fixture:

**Phase 7 — Backend Developer Application**

The proof demonstrates:

- pre-Membership Admission Context;
- structured candidate answers;
- private draft before submit;
- CV through the shared private Asset pipeline;
- exact Personal-Context portfolio evidence cited into the Admission application;
- explicit submit;
- reviewer Evaluation;
- no hidden Admission approval;
- no hidden Membership creation;
- candidate still denied ordinary GroupSpace access.

### Browser fixture

Seeder:

`database/seeders/Phase7InteractionDemoSeeder.php`

Run locally with:

~~~bash
php artisan db:seed --class=Phase7InteractionDemoSeeder
~~~

Guide:

`database/seeders/README-Phase7InteractionDemo.md`

The seeder is opt-in and restricted to local/testing environments. It does not alter the normal production seed path.

## Authorization and privacy properties

Proven boundaries include:

- Context authorization is required before structured interaction access;
- UUID possession does not grant access;
- own draft Submission is private from reviewers until submit;
- reviewers can see submitted/withdrawn attempts where authorized;
- outsider/cross-Context access is denied;
- Admission candidate structured interaction does not grant GroupSpace access;
- Evaluation authority is separate from Admission lifecycle authority;
- Submission files remain private and require authorization on retrieval;
- Personal Content evidence can be cited cross-Context only when the submitter can view the immutable source evidence.

## Evidence and historical correctness

A submitted attempt preserves exact provenance through:

- Submission UUID;
- Context UUID;
- submitter Actor;
- attempt number;
- Interaction Definition UUID + version + canonical hash;
- exact Content revision UUID + manifest hash where applicable;
- normalized Responses;
- Asset UUID/hash/MIME/size/file provenance;
- immutable Content evidence locator identity;
- canonical Submission evidence JSON;
- SHA-256 Submission evidence hash.

A finalized Evaluation similarly seals exact review evidence against the submitted attempt.

Neither later Content publication nor later Interaction Definition versions mutate that evidence.

## Migration proof

Phase 7 migrations are additive:

- `2026_09_22_200000_create_interaction_definition_kernel.php`
- `2026_09_22_210000_create_submission_response_kernel.php`
- `2026_09_22_220000_create_evaluations.php`

CI now performs:

~~~text
migrate
→ rollback latest 3 Phase 7 migrations
→ reapply migrations
→ migration status
→ scheduler / queue smoke
→ backup / restore smoke
→ full tests
~~~

GitHub Actions run `35772393444` proves all three rollback and reapply successfully on SQLite.

## Deliberate boundaries

Phase 7 deliberately does not implement:

- generic Conversation;
- chat-driven hidden state changes;
- Admission v2 conversation-first workspace;
- reviewer-internal Conversation;
- realtime/WebSocket/outbox infrastructure;
- generic Workflow;
- Admission approval from Evaluation;
- Membership finalization from Evaluation;
- negotiated Contract/Agreement;
- Planner;
- matching;
- reputation scoring.

These remain in their accepted downstream phases.

## Remaining human gate

The owner has completed the requested local engineering validation successfully.

Final browser acceptance is still open. During manual school-exam/employment use, responses could be entered, but reviewer-side consequence/discoverability was not clear enough to the owner. Before Phase 7 closes, determine whether this is:

- an actual reviewer-state/visibility defect; or
- a discoverability/composition issue in the current standalone Phase 7 UX.

Do not mark the phase human-accepted until that distinction is resolved and the reviewer/candidate consequences are understandable in browser use. The broader product direction is now anchored by the connected paid-work north-star scenario in `docs/PROJECT_COMPASS.md`.
