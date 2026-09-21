# Phase 3 — Concept Kernel Implementation Report

## Status

Phase 3 is active on `feat/phase-03-concept-kernel`.

- 3A — schema and authority contract: **complete and owner-local validated**.
- 3B — domain behavior: **implemented and remote-CI validated; owner-local validation pending**.
- 3C — publication evidence and final acceptance proof: **not started**.

Phase 3 is not complete until 3C and the final owner gate pass.

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

## Browser/UI status

3B adds no user-facing Concept screens or routes.

Expected browser result after synchronization:

- existing authentication/Group/invitation/content UI remains unchanged;
- `/up` remains healthy;
- no unfinished Concept-management UI is exposed.

A dedicated Concept UI is not required for the Phase 3 kernel acceptance gate.

## Remaining Phase 3 work — 3C

3C must still implement and prove:

1. exact `SpaceContentRevision` semantic assertions are included in sealed publication evidence;
2. later mutable catalog labels/classification do not rewrite historical sealed semantic evidence;
3. SpaceContent can be classified `about Chess`;
4. exact revision can carry a semantic predicate such as `teaches Chess`;
5. publication rejects semantic-evidence inconsistencies;
6. final Phase 3 focused/full/static/format/build gates pass;
7. owner-local migration/test/browser smoke passes;
8. final phase report/current-state/roadmap closure is committed.

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

Synchronize the owner-local checkout to the final 3B documentation head, run the focused Concept test plus the full/static/format gate, confirm existing browser behavior is unaffected, and only then begin 3C.
