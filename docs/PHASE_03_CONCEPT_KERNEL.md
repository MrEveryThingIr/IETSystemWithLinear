# Phase 3 — Concept Kernel

## Status

Active on `feat/phase-03-concept-kernel`, starting from accepted Phase 2 closure commit `73e98f05746c7c42bf63d7c9bc91bee63d800cf9`.

## Objective

Implement one reusable semantic identity/classification kernel that Actor/Profile, Content, later Contexts, Blueprints, Need/Offer, and discovery can reuse without creating parallel category tables.

Architecture authority: `docs/CONCEPT_KERNEL.md`.

## Included scope

Phase 3 implements:

- Concept Vocabulary with platform/Actor/Group scope;
- Concept identity and active/deprecated/merged lifecycle;
- multilingual labels/synonyms/abbreviations;
- classification Schemes and membership;
- polyhierarchical broader/narrower edges;
- transactionally rebuilt closure for ancestor/descendant queries;
- trusted semantic relation-type registry and Concept relations;
- generic controlled-predicate Concept Assertions;
- explicit Concept governance authorization;
- exact SpaceContentRevision semantic publication evidence;
- factories and focused proof tests.

## Frozen design decisions

- a Concept represents semantic identity, not a contextual role such as skill/need/tag;
- skill, interest, need, topic, teaching, etc. are assertion predicates;
- one Concept can have many parents and belong to many Schemes;
- hierarchy edges are authoritative; closure is derived and never authored directly;
- assertion subject aliases are controlled by the Concept kernel and do not change Laravel's global morph map;
- Group/Actor/Content identity is not replaced by Concept;
- published revision semantics bind exact Concept UUIDs/predicates into publication evidence;
- later Concept label/catalog changes do not rewrite sealed historical revision evidence;
- duplicate Concepts are merged/deprecated, not deleted/reused.

## Implementation milestones

### 3A — schema and authority contract

- catalog/graph/assertion migrations;
- trusted predicate/source/visibility enums;
- explicit `manage_concepts` platform and Group authority;
- active-phase documentation.

### 3B — domain behavior

- Eloquent models/factories/relationships;
- Vocabulary policy;
- create/catalog actions;
- hierarchy mutation action with cycle prevention + closure rebuild;
- merge/deprecate lifecycle;
- trusted relation action;
- subject-authorized assertion action.

### 3C — publication evidence and acceptance proof

- exact revision assertion integration into Content publication manifest;
- sealed-revision semantic immutability;
- proof suite for polyhierarchy, labels, assertions, authorization, merge lifecycle and revision evidence;
- final validation/report.

## Explicitly excluded

Do not implement in Phase 3:

- progressive Profile UI/domain;
- generic Context/Admission Context;
- recommendation/ranking;
- AI automatic taxonomy;
- giant seeded ontology;
- Content Blueprint fields;
- Need/Offer matching;
- generic Workflow;
- negotiated Contract;
- WebSocket/broadcast product features.

## Acceptance proof

Phase 3 closes only when tests prove:

1. one Chess Concept appears below two hierarchy parents without duplicate Concept identity;
2. ancestor/descendant closure is correct after edge insertion and removal;
3. cycle insertion is rejected without partial graph mutation;
4. the same Actor simultaneously has `has_skill Chess` and `wants_to_learn Chess`;
5. SpaceContent can be classified `about Chess`;
6. exact SpaceContentRevision semantic assertions are sealed into publication evidence and cannot be added/rewritten after sealing;
7. multilingual preferred/synonym labels resolve without changing Concept identity;
8. Concept merge preserves source UUID/history and points current discovery to the target;
9. unauthorized users cannot mutate platform/Actor/Group Vocabulary or Scheme structure.

## Exit gate

- focused Concept tests green;
- full PHPUnit suite green;
- PHPStan clean;
- Pint clean for Phase 3 PHP;
- Vite build green;
- migrations run cleanly on a fresh CI database and the synchronized owner-local database;
- implementation report committed;
- human owner accepts the Phase 3 kernel;
- Phase 4 becomes next only after this gate.
