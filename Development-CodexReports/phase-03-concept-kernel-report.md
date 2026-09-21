# Phase 3 — Concept Kernel Implementation Report

## Status

Phase 3 is active on `feat/phase-03-concept-kernel`.

- 3A — schema and authority contract: **complete and owner-local validated**.
- 3B — domain behavior: **complete and owner-local validated**.
- 3C — publication evidence and final acceptance proof: **implemented and remote-CI validated; owner-local final validation pending**.

Phase 3 is not complete until the final owner-local/browser gate passes.

## Starting point

- Accepted Phase 2 closure branch: `feat/phase-02-delivery-operations`.
- Accepted Phase 2 closure commit: `73e98f05746c7c42bf63d7c9bc91bee63d800cf9`.
- Phase 3 branch: `feat/phase-03-concept-kernel`.
- Phase 3 architecture: `docs/CONCEPT_KERNEL.md`.
- Phase 3 execution contract: `docs/PHASE_03_CONCEPT_KERNEL.md`.

## Milestone 3A — schema and authority contract

Implemented:

- controlled vocabulary-scope/status/label/assertion registries;
- explicit platform and Group `manage_concepts` authority;
- Concept Vocabulary / Concept / Label / Scheme / Scheme Membership schema;
- hierarchy edges + derived closure schema;
- trusted relation registry + relation schema;
- generic Concept Assertion schema;
- migration backfill granting existing Group Owner roles `manage_concepts`;
- Phase 3 active-state documentation.

### Owner-local validation

Reported by the owner after synchronization:

- full PHPUnit suite: **284 passed / 1441 assertions**;
- PHPStan: **no errors**;
- Pint over Phase 3A PHP: **12 files passed**;
- working tree: **clean**.

No Concept UI exists in 3A, so no new browser behavior was expected.

## Milestone 3B — domain behavior

Implemented:

### Models and factories

- `ConceptVocabulary`;
- `Concept`;
- `ConceptLabel`;
- `ConceptScheme`;
- `ConceptSchemeMembership`;
- `ConceptHierarchyEdge`;
- derived/read-only `ConceptClosure`;
- trusted/read-only `ConceptRelationType`;
- `ConceptRelation`;
- `ConceptAssertion`;
- factories for the authorable semantic records.

### Governance

- `ConceptVocabularyPolicy`:
  - platform Vocabulary requires platform `ManageConcepts`;
  - Actor Vocabulary requires the same active verified User→Actor identity;
  - Group Vocabulary requires Group `manage_concepts`.
- `ConceptAssertionPolicy`:
  - Actor assertions require self authority;
  - Group assertions require Group concept-management authority;
  - SpaceContent assertions reuse existing Content update authority;
  - SpaceContentRevision assertions reuse authority over their parent Content.
- `GroupPolicy::manageConcepts()` uses the explicit Group permission.

### Actions

- `CreateConceptVocabulary`;
- `CreateConcept`;
- `SetConceptLabel`;
- `CreateConceptScheme`;
- `AddConceptToScheme`;
- `ManageConceptHierarchy`;
- `RebuildConceptClosure`;
- `ManageConceptLifecycle`;
- `RelateConcepts`;
- `AssertConcept`.

### Integrity behavior

- hierarchy mutations serialize on the Scheme row;
- closure is rebuilt transactionally from authored hierarchy edges;
- self-parent edges are rejected;
- cycle insertion is rejected;
- Scheme hierarchy requires Scheme membership;
- merged Concepts preserve their identity and resolve new authoring to the canonical active Concept;
- symmetric relations normalize direction and are idempotent;
- assertion predicates remain distinct, so one subject may validly hold multiple predicates to the same Concept;
- assertion subjects use the controlled kernel registry rather than arbitrary PHP class names;
- exact revision assertions already refuse mutation after a revision has a verifiable sealed manifest; manifest inclusion itself belongs to 3C.

### Defect found and fixed during CI

The first behavioral run exposed a stale-object defect: an already-loaded source Concept still appeared active immediately after another instance merged it, allowing a new assertion to target the stale source ID.

Fix:

- `Concept::canonical()` now begins from fresh persisted state before following the merge chain.

This preserves the rule that **new authoring resolves toward the canonical Concept even when callers hold a stale model instance**.

## Milestone 3B remote validation

Final remote CI on runtime head `da993619c921773157934a77c002aa1411ccf80c`:

- changed-file Pint: passed;
- PHPStan: **no errors**;
- migrations: passed on fresh SQLite;
- scheduler/queue smoke: passed;
- SQLite backup→restore smoke: passed;
- full PHPUnit suite: **290 passed / 1463 assertions**;
- Composer advisory audit: no security vulnerability advisories;
- npm install/audit/build gates: passed.

Focused 3B tests prove:

- multilingual labels preserve one Concept identity;
- Chess-style polyhierarchy produces correct closure;
- removing an edge rebuilds closure correctly;
- cycle insertion is rejected without persisting the bad edge;
- one Actor can hold multiple predicates to the same Concept;
- merged source identity/UUID is preserved while new assertions target the canonical Concept;
- symmetric relations are idempotent in either direction;
- Group Concept governance is isolated across Groups.

## Owner-local milestone 3B validation

The owner synchronized the 3B head and reported:

- `php artisan migrate`: nothing pending;
- all four Phase 3 migrations: ran;
- focused `ConceptKernelDomainTest`: **6 passed / 22 assertions**;
- full PHPUnit suite: **290 passed / 1463 assertions**;
- PHPStan: no errors;
- Pint over Phase 3 changes: **32 files passed**;
- Vite production build: passed;
- working tree: clean.

## Milestone 3C — publication semantic evidence

Implemented:

- Content manifest contract upgraded to version 3;
- deterministic `semantic_assertions` snapshot for exact SpaceContentRevision assertions;
- semantic snapshot uses stable assertion/Concept/Scheme UUID evidence rather than mutable labels or internal Actor IDs;
- assertion source, visibility, weight/confidence, validity window and metadata are sealed;
- revision semantic authoring locks the revision row, serializing against publication;
- post-publication revision assertions cannot be added or rewritten;
- later Concept label changes and merge lifecycle do not rewrite the sealed historical manifest;
- all revision-cloning Content actions copy semantic assertions into the new draft with new assertion UUIDs and `copied_from_assertion_uuid` provenance;
- mutable SpaceContent-level classification remains distinct from immutable exact-revision evidence.

### Defects caught and fixed during 3C

- an attempted public Actor UUID in the semantic manifest was removed because Actor currently has no UUID; Phase 3 did not expand Actor identity scope merely for publication provenance;
- JSON metadata handling was normalized for cross-database drivers;
- PHPStan caught an impossible empty-metadata branch after copy provenance was always added;
- legacy Content tests expecting manifest v2 were aligned with the intentional v3 contract.

### 3C remote validation

GitHub Actions on runtime head `fc22e847acc91af2fe6150fdaa4cc4511901d99f`:

- changed-file Pint: passed;
- PHPStan: **no errors**;
- fresh migrations: passed;
- scheduler/queue smoke: passed;
- SQLite backup→restore smoke: passed;
- full PHPUnit suite: **292 passed / 1493 assertions**;
- Vite/npm gates: passed;
- Composer advisory audit: no security vulnerability advisories.

## Browser/UI status

Phase 3 deliberately exposes no half-built Concept administration surface.

After final synchronization the browser should show the same stable product UI:

- authentication/Groups/invitations/Content continue to render normally;
- `/up` remains healthy;
- published Content rendering is unchanged visually;
- no Concept/Profile menu is expected yet.

The Phase 3 effect is architectural: Content and future Profile/Context interfaces can now safely consume the same semantic kernel. User-visible Profile/Concept selection/sharing experiences begin in Phase 4 and later focused UX phases.

## Remaining Phase 3 work

Only the owner-local final gate remains:

1. synchronize the final 3C head;
2. run focused Concept domain + publication-evidence tests;
3. run full PHPUnit, PHPStan, Pint and Vite build;
4. verify migrations remain current and working tree clean;
5. browser-smoke `/up`, login, one Group page and one published Content page;
6. commit the final closure documentation marking Phase 4 next.

## Explicitly deferred

Phase 3 still does not implement:

- progressive Profile;
- generic Context or Admission Context;
- Admission v2;
- recommendation/ranking;
- AI taxonomy generation;
- giant ontology seeds;
- Content Blueprints;
- Need/Offer matching;
- generic Workflow;
- negotiated Contract;
- real-time broadcasting.

## Next gate

Synchronize the owner-local checkout to the final 3C documentation head and complete the final local/browser gate. If green, close Phase 3 and activate Phase 4 — Actor/Party and progressive Profile.
