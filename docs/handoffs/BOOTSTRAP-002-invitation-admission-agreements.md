# BOOTSTRAP-002 — Complete invitation, admission, and group-agreement workflows

## Task state

- **Status:** Ready to merge
- **Responsible arm:** Codex escalation arm; human owner and ChatGPT decision hub for final review
- **Next step:** Review and commit the verified implementation, then define the Spaces and Content milestone
- **Risk:** Medium; authorization, workflow state, immutable evidence, and membership creation

## Objective

Make invitation-oriented registration, admission review, agreement acceptance/version management, and membership finalization complete, discoverable, auditable, and user-friendly before beginning Spaces and Content.

## Authoritative inputs

1. Human-reported invitation, redirect, admission-review, message-visibility, and agreement-management defects.
2. `docs/PROJECT_COMPASS.md`, especially the Invitation/Application, Agreement, Membership, and Contract distinctions.
3. `docs/DEVELOPMENT_CIRCUIT.md`.
4. Repository instructions and the existing Laravel implementation and tests.

## Accepted decisions and invariants

- New account registration is invitation-oriented and requires a valid invitation token.
- Possession or redemption of an invitation creates a resumable Admission, not Membership.
- Membership is created only after review approval and acceptance of every currently active agreement required for admission.
- Applicant messages, reviewer notes, decisions, and acceptance evidence are durable admission history and are visible before Spaces exists.
- Finalization is a terminal admission transition and transfers accepted admission agreement evidence to the resulting Membership.
- Group Agreements remain distinct from Contracts. Contracts are a later domain with explicit parties and obligations.
- Approved or scheduled Group Agreement versions may be activated immediately by an authorized group manager; the previous active version is preserved as superseded history.
- Published terms and evidence remain immutable.

## Scope

### Included

- Invitation-scoped login and registration redirects and persistent invitation tracking.
- Admission state/action correction, reviewer queue, visible history, required-agreement filtering, and finalization state.
- Group Agreement discoverability, version content/history UI, clean revision forms, immediate activation, scheduling, and reacceptance navigation.
- Transaction, idempotency, authorization, and concurrency protections for the affected operations.
- MySQL-safe foundation migration corrections already present in the working task.
- Feature coverage for complete user journeys and high-value failures.

### Excluded

- Spaces, posts, articles, diaries, and other Content implementation.
- The separate Contract, obligation, and settlement domains.
- Email or push notifications for admission state changes.
- Changing unresolved platform bootstrap-registration semantics beyond the accepted invitation-oriented flow.

## Files and systems examined

- Invitation, Admission, Group, Membership, Agreement, version, acceptance, and event models/actions/policies.
- Authentication and invitation routes and Livewire components.
- Group, invitation, admission, agreement, and reacceptance Blade interfaces.
- Foundation and lifecycle migrations, scheduler registration, policies, and related feature tests.
- Project compass, development circuit, and existing handoff convention.

## Changes made

- Preserved invitation context through login, registration, verification, and admission creation.
- Added persistent sent/received invitation and invitee tracking.
- Rendered immutable admission history so applicant and reviewer messages are visible to both sides.
- Added a group-level admissions review queue and direct admission links.
- Added the terminal `finalized` admission state and `finalized_at` timestamp.
- Made candidate transitions and agreement acceptance transactional and row-locked.
- Prevented duplicate admission acceptance events.
- Copied required admission agreement acceptances into Membership evidence during finalization.
- Limited the admission screen to active agreements explicitly required for admission.
- Added discoverable Group Agreement management with full version text, state, rationale, decision notes, revisions, scheduling, and immediate activation.
- Added deterministic scheduled-version activation order and explicit agreement reacceptance navigation.
- Corrected affected interface encoding and state-specific guidance.

## Commands and validation

- `php artisan migrate --no-interaction` — passed; `2026_09_10_192438_add_finalized_at_to_admissions_table` applied.
- Focused invitation/admission/agreement suite — passed: 17 tests, 107 assertions.
- `php artisan test --compact` with an isolated compiled-view path — passed: 100 tests, 470 assertions.
- `vendor/bin/phpstan analyse --no-progress` — passed with 0 errors.
- `vendor/bin/pint --dirty --format agent` — passed.
- `npm.cmd run build` — passed with Vite 8.2.2.
- `git diff --check` — passed before final handoff update; rerun in final state.

Non-blocking environment notices: PHPUnit could not update its result-cache file because another process or filesystem permissions held it; test execution still completed successfully. Vite reported the existing optional `fontaine` optimization notice.

## Risks and unresolved questions

- Real-browser automation was not run; rendered Livewire behavior is covered by feature tests and production assets compile successfully.
- Future-dated agreement activation requires the Laravel scheduler to be running in the deployed environment; immediate activation does not.
- Admission state changes are visible in-app but do not yet send asynchronous notifications.
- The precise Spaces/Content authorship, moderation, visibility, commenting, revision, and media semantics must be frozen before that implementation begins.

## Repository linkage

- **Branch:** `FIX_BY_VSCODE_AGENT`
- **Commit(s):** Pending for this correction set; current HEAD before final commit is `9c424c3`
- **Pull request:** Not created
- **Linear issue:** Not created

## Review findings

The original applicant message was stored but invisible. Owners lacked a review queue. Optional agreements were presented as required. Admission finalization did not become terminal and failed to transfer acceptance evidence to Membership, which could immediately lock a newly admitted member out. Agreement management was hidden and its shared form state made multi-agreement revisions confusing. These defects are corrected and covered by observable behavior tests.

## Final outcome

The invitation, admission, and Group Agreement phase is implementation-complete and passes the repository quality gates. It is ready for human/ChatGPT review and merge. Spaces and Content remain intentionally unimplemented.

## Prompt for next step

```text
TASK: Define and implement the first Group Spaces and Content milestone.
RESPONSIBLE ARM OR DECISION GATE: Human owner + ChatGPT decision hub for semantics; Linear for accepted implementation work.
AUTHORITATIVE INPUTS: docs/PROJECT_COMPASS.md; docs/DEVELOPMENT_CIRCUIT.md; docs/handoffs/BOOTSTRAP-002-invitation-admission-agreements.md; merged GitHub source and CI evidence.
CURRENT VERIFIED STATE: Invitation-oriented registration, admission review/history, required Group Agreements, version activation, acceptance evidence, and membership finalization pass 100 tests with 470 assertions.
ACCEPTED DECISIONS: Space uses Group participation; Content is authored intrinsic information; Group, Space, Content, Agreement, and Contract remain distinct; published revisions are immutable.
OBJECTIVE: Deliver the smallest complete Group Space and authored Content flow.
INCLUDED: Decide Space visibility and creation authority; Content types and lifecycle; authorship; revision; moderation; membership access; initial posts/articles/diary use cases.
EXCLUDED: Contract, commerce, ledger, planner, reputation, and generic low-code builders.
ACCEPTANCE CRITERIA: Must be frozen in Linear before implementation.
REQUIRED TESTS: Authorization matrix, tenant isolation, lifecycle, rendering/escaping, query behavior, and complete author/reader journeys.
RISKS: Unresolved visibility, moderation, ownership, revision, deletion, media, and content-type semantics.
REQUIRED OUTPUT: Accepted Linear issue, implementation, GitHub review evidence, and an updated living handoff.
STOP CONDITIONS: Stop for unresolved product semantics, frozen-boundary changes, destructive operations, or incomplete validation.
```
