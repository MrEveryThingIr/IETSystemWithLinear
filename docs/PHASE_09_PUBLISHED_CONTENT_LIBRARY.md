# Phase 9 — Published Content Library and Reference/Placement Semantics

## Status

Remote implementation complete and green on `feat/ideal-v1-09-content-library-placement`.

Runtime checkpoint:

~~~text
SHA: fdb7c1cbfbe1a81c284c67df79a71b0e5ee3074a
GitHub Actions: 36020336046
432 tests / 2413 assertions
Pint: 378 files
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Baseline:

`7faa3ea152c7b3f1aee89be0d7bc38aceec94107`

## Purpose

Make published Content reusable across authorized Contexts without copying the artifact, moving its ownership, or weakening exact historical evidence.

Phase 9 distinguishes two meanings that must never collapse:

~~~text
current presentation
ContentPlacement → same Content identity → current published revision

historical evidence
ContentEvidenceReference → exact sealed revision + optional exact target
~~~

## Architecture

Content keeps one home Context.

~~~text
home Context
   ↓ owns / authors
Content
   ↓ publishes sealed revisions
   ├── ContentPlacement → target Context presentation
   └── ContentEvidenceReference → exact historical revision/target
~~~

`ContentPlacement` records target Context, source Content, placer, active/removed lifecycle and durable UUID. It does not duplicate `SpaceContent`, revisions, Assets or Blocks.

Removing and later re-presenting the same Content to the same target reactivates the placement identity instead of creating duplicate provenance.

## Library

The Content Library is viewer-authorized and publication-only.

Implemented filters:

- free-text search;
- Blueprint/type;
- semantic Concept/topic.

Cards expose source Context, author, purpose/Blueprint, Concept labels, cover image where available, active placements and permission-aware presentation controls.

Drafts and unsealed/unverifiable publication state are excluded.

## Authorization

Placement intentionally uses a strong dual-authority rule.

The acting user must:

1. be allowed to read the Content through its home/source Context; and
2. be allowed to manage Content in the target Context.

This prevents a target-only reader from using one placement as a bridge to reshare the Content into arbitrary third Contexts.

A placement grants the target audience read access to the published artifact only. It does **not** grant:

- source/home Context visibility;
- Studio/edit/revision-management authority;
- authoring authority;
- interaction authority inherited from the home Context;
- Group/platform authority;
- automatic permission to place the artifact again.

Removal immediately revokes target-only placement access.

## Evidence and publication behavior

Normal placement means “present this artifact as currently published”.

Therefore a later publication becomes the edition shown through the placement.

An existing `ContentEvidenceReference` remains bound to its exact sealed revision and optional field/block/Asset/relationship target. Later edits/publications cannot rewrite what earlier evidence meant.

## Negative guarantees

- no `group_posts`, `personal_articles`, duplicate Album/Diary/Evidence tables;
- no copying Content just because it appears in another Context;
- no reassignment of Content home Context;
- no source authority derived from target access;
- no transitive resharing through placement;
- no historical evidence drift;
- no Match, Contract, Membership, payment or reputation consequence from presentation itself.

## Story proof

Alice publishes an Article or Album in its home Context.

An authorized presenter who independently has source-read authority and target-management authority presents that same artifact in Maple Housing Office.

Bob, who can read the target Context, can consume the presented publication without acquiring source/Studio authority.

An exact Evidence Reference can point to one revision/block. After Alice publishes a newer edition, the normal placement shows the newer publication while the Evidence Reference continues resolving the old exact target.

Removing the placement removes Bob's target-only access.

## Remote validation

GitHub Actions run `36020336046` on `fdb7c1cbfbe1a81c284c67df79a71b0e5ee3074a` proves:

- **432 passed / 2413 assertions**;
- changed-file Pint: **378 files**;
- PHPStan: no errors;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler smoke: passed;
- database queue smoke: passed with no failed jobs;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused Phase 9 regression coverage proves:

- target placement grants published read without source/Studio authority;
- a placement-only reader cannot transitively reshare;
- removal revokes target-only access;
- re-presentation reactivates the same placement identity;
- normal placement follows later publication;
- exact evidence remains pinned to its historical revision.

## Known limitations / deferred polish

- no cross-party placement request/approval workflow yet; one actor currently needs both source-read and target-management authority;
- Library filters do not yet include every roadmap refinement such as author/language/date;
- placement is intentionally read-oriented and does not delegate home-Context interaction authority;
- compact ⋮ action menus and broader responsive/RTL/accessibility polish remain in the cumulative UX pass;
- local/browser acceptance is deferred to `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

## Exit gate

Phase 9 exits when:

- the published Library is authorization-safe;
- cross-Context presentation reuses one Content identity;
- placement cannot escalate source or transitive authority;
- placement lifecycle is durable and revocable;
- current-presentation and exact-evidence semantics are demonstrably different;
- canonical docs/System Manual/acceptance worksheet are synchronized;
- full remote CI is green.
