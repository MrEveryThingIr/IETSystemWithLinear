# Phase 5 — Generic Content Context Implementation Report

## Status

**Phase 5 runtime implementation is technically complete and remote-CI green. Final owner-local/browser acceptance is pending.**

Branch:

`feat/phase-05-content-context`

Starting accepted Phase 4 baseline:

`fef290d2f1d58f69ddab1fbfd00ec68ff2a186d7`

Frozen Phase 5 runtime candidate:

`34bd6b8957e4ecc2b0474bc0b7d163ae010dc749`

Final runtime GitHub Actions run:

`35725999556`

Proof on that exact runtime commit:

- PHPUnit: **344 passed / 1807 assertions**;
- PHPStan: **no errors**;
- Pint changed-file gate: **216 files passed**;
- Vite production build: passed;
- fresh migrations: passed;
- migration / scheduler / database queue smoke: passed;
- SQLite backup → restore smoke: passed;
- npm audit: **0 vulnerabilities**;
- Composer security audit: clean.

Phase 6 remains blocked until the human owner completes the final local sync/test/browser/mobile/RTL acceptance gate.

## 5A — Context identity and authorization foundation

5A completed first and was frozen at:

`5dedada9796e8758760de209aea885c90e562340`

Remote proof at that checkpoint:

- **338 tests / 1763 assertions**;
- PHPStan clean;
- Pint **188 files**;
- build/operations/backup/security gates green.

Implemented:

- first-class `Context` with stable UUID and explicit kind;
- `personal`, `group_space`, and `admission` Context kinds;
- explicit relational subtype bindings rather than polymorphic owner columns:
  - `personal_contexts`;
  - `group_space_contexts`;
  - `admission_contexts`;
- one Context per backing Actor / GroupSpace / Admission;
- idempotent:
  - `EnsurePersonalContext`;
  - `EnsureGroupSpaceContext`;
  - `EnsureAdmissionContext`;
- deterministic GroupSpace Context backfill;
- Context policy abilities for view/create/interact/manage/definition management;
- candidate Admission Context access before Membership;
- reviewer Admission Context access through existing `manageAdmissions` authority;
- terminal Admission mutation denial;
- GroupSpace Context authorization delegated to existing GroupSpace policies.

### Maintenance-path defect caught

The repository seeders can disable Eloquent model events. Context UUID generation therefore could not depend only on a model `creating` event.

The provisioning Actions now write UUID identity explicitly, while the model hook remains a convenience fallback. Regression coverage proves provisioning works under `Model::withoutEvents(...)`.

## 5B — Context-bind the Content substrate

Implemented:

- migration `2026_09_22_160000_bind_content_substrate_to_contexts.php`;
- canonical `context_id` added to:
  - `space_contents`;
  - `space_content_definitions`;
  - `space_content_render_templates`;
  - Content-related `assets`;
- deterministic existing GroupSpace data backfill through `group_space_contexts`;
- legacy `group_space_id` retained as a compatibility column;
- non-Group Context Content uses `group_space_id = null`;
- Context/legacy-Group provenance checked by `ContextScope`;
- Context relations added to Content, Definitions, templates and Assets;
- existing Group Content factories/actions populate Context identity correctly;
- Content policy moved to Context authorization while preserving the legacy Group creation seam;
- Definition policy moved to Context authorization;
- same-Context integrity enforced for:
  - Definition ↔ Content;
  - saved Render Templates;
  - Content Assets;
  - annotation Assets;
  - parent/child Content composition;
- Content media storage moved to Context storage segments;
- generic and Group asset delivery both verify Context identity;
- publication relationship sealing now requires same Context;
- existing sealed publication manifest/hashes were **not rewritten**;
- Profile Assets remain valid with both `context_id = null` and `group_space_id = null`.

### Migration / rollback hardening

The compatibility migration was corrected for SQLite foreign-key rollback behavior.

Rollback is intentionally blocked once non-Group Context Content/Assets exist; the migration does not fabricate fake GroupSpaces simply to force old schema shape.

## 5C — Personal and Admission Content proof

Implemented generic domain Actions:

- `CreateContextContentDefinition`;
- `CreateContextContent`.

Implemented application entry points:

- `/my-content` → the authenticated Actor's Personal Context;
- `/admissions/{admission}/content` → Admission Context;
- generic `/contexts/{context}/contents/...` Content routes;
- generic Context Asset stream/download routes.

Implemented generic Context Content UI:

- Context-local simple Definition creation + activation;
- structured draft creation;
- revision editing;
- publishing;
- Context Content listing;
- read-only rendering;
- actor identity presentation;
- responsive action layout;
- English/Persian/Arabic/Simplified-Chinese strings.

The first non-Group UI intentionally uses a simple local “note/body” Definition experience. The underlying domain Actions remain generic, but productized reusable Blueprints belong to Phase 6.

### Personal proof

An active verified Actor can:

- lazily provision exactly one Personal Context;
- create/activate a Context-local Definition;
- create/revise/publish Content;
- attach media and interact with Content;
- use generic Context asset delivery;
- do all of that with **no Group or fake GroupSpace**;
- deny unrelated Actors.

### Admission proof

Before Membership:

- candidate and authorized reviewer share one Admission Context;
- reviewer can create/manage Context-local Definitions;
- candidate can author/revise/publish their own Content;
- reviewer can manage Admission Context Content;
- unrelated Actors are denied;
- candidate remains denied ordinary GroupSpace access;
- no Group Membership is created as a side effect.

### Terminal Admission behavior

Terminal Admissions are read-only.

The final audit separated three concerns that must not be conflated:

- **Context view** — historical access;
- **revision/draft review** — historical private collaboration access;
- **mutation/interactions** — only while the Admission is mutable.

Candidate and reviewer can therefore still read historical draft collaboration after rejection/cancellation/finalization, while update/publish/interaction authority is denied.

## Eagle-eye defects caught before the final candidate

The Phase 5 implementation intentionally stopped at several intermediate CI gates. Those gates caught real cross-layer issues:

1. required Context UUID identity initially depended too heavily on Eloquent events;
2. the Content compatibility migration needed warning-safe and SQLite-safe rollback handling;
3. GroupSpace-only policy assumptions remained in Definition authorization;
4. media/annotation/composition paths needed same-Context checks, not just Content rows;
5. generic Context listings initially risked exposing drafts the viewer could not individually view;
6. an unauthorized generic route/import path needed cleanup;
7. terminal Admission Content became correctly immutable but initially also became unreadable because draft visibility reused mutation authority;
8. after separating authorization, the generic Content page still used `canUpdate` as a proxy for draft visibility, producing a 404 for valid read-only historical access.

All are resolved and covered before the frozen candidate.

## Preserved architecture boundaries

Phase 5 did **not** pull later phases forward:

- no Content Blueprint catalog/versioning — Phase 6;
- no Submission/Response/Evaluation engine — Phase 7;
- no Admission questionnaire/requirements/evidence engine or persistent candidate-reviewer Conversation — Phase 8;
- no realtime event/outbox/broadcast architecture — Phase 9;
- no Workflow Kernel — Phase 10;
- no Planner Occurrence model — Phase 11;
- no Need/Offer matching — Phase 13;
- no Proposal/Negotiation/Contract/Commitment/Fulfillment — Phase 14.

Other preserved invariants:

- User remains authentication identity;
- Actor remains participant identity;
- Group Membership is not Context authorization;
- Admission Context authority does not grant ordinary Group access;
- Profile disclosure is not Context authorization;
- Content wording is not domain authority;
- existing sealed publication evidence remains immutable;
- no fake Group represents Personal Content.

## Remaining human gate

Remote/runtime implementation is frozen at:

`34bd6b8957e4ecc2b0474bc0b7d163ae010dc749`

The remaining Phase 5 gate is owner-local validation:

- synchronize the branch;
- apply migrations on the owner's existing database;
- focused Context tests;
- full PHPUnit;
- PHPStan;
- Pint;
- Vite production build;
- clean diff/tree;
- browser check of Personal Context Content;
- browser check of Admission candidate/reviewer collaboration before Membership;
- terminal Admission read-only historical Content;
- existing Group Content regression;
- narrow mobile + RTL acceptance.

Only after that gate succeeds should Phase 5 be marked formally closed and Phase 6 begin.
