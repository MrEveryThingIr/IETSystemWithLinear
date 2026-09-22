# Phase 5 — Generic Content Context

## Status

**Active** on `feat/phase-05-content-context`.

Starting baseline:

`fef290d2f1d58f69ddab1fbfd00ec68ff2a186d7`

That commit formally closes and human-accepts Phase 4. Phase 5 is the only active implementation phase.

## Objective

Remove the architectural requirement that Content belong to a GroupSpace while preserving all accepted Group Content behavior.

Phase 5 introduces the first production Context Kernel and proves three bounded environments:

1. GroupSpace Context — compatibility with all existing Group Content;
2. Personal Context — private Content owned by one Actor without a fake Group;
3. Admission Context — candidate/reviewer collaboration before Membership without ordinary Group access.

The phase must migrate incrementally. Existing Group Content, routes, publication evidence and authorization must continue to work while the generic Context path is introduced.

## Frozen boundaries

Phase 5 must preserve:

- User is authentication identity;
- Actor is participant identity;
- Group is shared-governance membership;
- Context is a bounded collaboration/artifact environment;
- Group Membership is not universal Context authorization;
- Admission candidate access does not imply Group participation;
- Profile disclosure is not Context authorization;
- conversation/content wording is not domain authority;
- published Content/revision evidence remains immutable;
- existing sealed publication manifests/hashes are not silently rewritten;
- no fake one-person Group may be created to support Personal Content.

Phase 5 must not pull forward:

- Admission v2 questionnaires/submissions/evidence requirements from Phase 8;
- realtime broadcasting from Phase 9;
- Workflow Kernel from Phase 10;
- Planner Occurrences from Phase 11;
- Need/Offer matching from Phase 13;
- Proposal/Negotiation/Contract/Commitment/Fulfillment from Phase 14;
- Content Blueprints from Phase 6.

## Context identity design

Use a first-class `contexts` table with stable UUID identity and a small explicit kind enum.

Initial kinds:

- `personal`;
- `group_space`;
- `admission`.

Do **not** use a generic polymorphic `contextable_type/contextable_id` pair. Context bindings require database-enforced foreign keys.

Use explicit subtype-binding tables:

- `personal_contexts`: one Context ↔ one Actor;
- `group_space_contexts`: one Context ↔ one GroupSpace;
- `admission_contexts`: one Context ↔ one Admission.

Each backing object may have at most one Context of that kind.

This keeps Context identity generic while keeping domain provenance relational and auditable. Future DirectCollaboration, Negotiation, Contract and Project bindings may add their own explicit subtype table when their roadmap phase exists.

Context itself does not duplicate the lifecycle of the backing domain. Whether a Context is usable is derived from its binding:

- GroupSpace lifecycle remains authoritative for GroupSpace Context;
- Admission lifecycle remains authoritative for Admission Context;
- Actor/account state remains authoritative for Personal Context.

## Context provisioning

Dedicated idempotent Actions must provide:

- `EnsurePersonalContext`;
- `EnsureGroupSpaceContext`;
- `EnsureAdmissionContext`.

Existing GroupSpaces are backfilled to one Context during migration.

New GroupSpace creation must guarantee Context availability through the normal GroupSpace creation path, while tests/factories may safely resolve it idempotently.

Personal and Admission Contexts may be created lazily on first authorized use, but a read must never create or mutate another Actor's unrelated Profile state.

## Authorization contract

Introduce one Context authorization seam rather than sprinkling kind checks through Content.

Minimum policy abilities:

- `view`;
- `createContent`;
- `interactContent`;
- `manageContent`;
- `manageDefinitions`.

### GroupSpace Context

Delegate to the existing `GroupSpacePolicy` semantics.

No existing Group Content authorization may become broader.

### Personal Context

Only the active verified User acting as the bound active Actor may:

- view;
- create;
- interact;
- manage Content/Definitions.

No other User receives access merely because a Profile is public or selectively shared.

### Admission Context

Candidate access and reviewer access are independent from Membership.

Candidate:

- must be the active verified User's active Actor matching `candidate_actor_id`;
- may view the Admission Context before Membership;
- may create/interact while the Admission remains non-terminal.

Reviewer:

- must be an active verified User/Actor;
- must hold existing authorization to manage/review Admissions for the Admission's Group;
- may view and collaborate without granting the candidate ordinary Group access.

Terminal Admissions (`finalized`, `rejected`, `cancelled`) become read-only in Phase 5. Historical access remains subject to the Context policy; mutation is denied.

Phase 5 does not create a reviewer-internal audience, structured Submission, requirement engine or Conversation. Those remain Phase 8 work.

## Content compatibility migration

Existing class/table names such as `SpaceContent` and `space_contents` remain during Phase 5. Do not perform a destructive mass rename to `Content`.

Add a canonical `context_id` binding to the existing Content substrate:

- `space_contents`;
- `space_content_definitions`;
- `space_content_render_templates`;
- `assets`.

Existing Group-scoped rows are backfilled through their GroupSpace Context.

### Legacy GroupSpace columns

Keep existing `group_space_id` columns during the compatibility period.

For Content, Definitions and saved render templates:

- existing GroupSpace-backed rows retain `group_space_id`;
- Personal/Admission rows use `context_id` and have no GroupSpace;
- model/action invariants require a GroupSpace Context row's legacy `group_space_id` to match its Context binding.

The migration may make legacy `group_space_id` nullable where required for non-Group Contexts.

For Assets:

- `context_id` is nullable because Profile-image Assets legitimately belong to neither Content Context nor GroupSpace;
- Assets created for Content/annotations must carry the same Context as the Content;
- existing Group Content Assets are backfilled to their GroupSpace Context;
- Profile Assets remain `context_id = null` and `group_space_id = null`.

## Definition and presentation scope

Definitions remain context-scoped in Phase 5.

A Definition used to create Content must belong to the same Context as the Content.

Saved render templates also become context-scoped while retaining GroupSpace compatibility.

Phase 5 does not introduce global Content Blueprints. Reusable productized authoring belongs to Phase 6.

## Relationships, interactions and media

Where current runtime invariants say “same GroupSpace”, Phase 5 moves new authorization/integrity decisions to “same Context”.

This applies to at least:

- parent/child Content composition;
- Definition/Content compatibility;
- Content media Assets;
- annotation attachment Assets;
- saved render templates.

The existing annotation storage value `space` is retained for backward compatibility. Runtime may expose a context-oriented alias/label, but no destructive data rewrite is required in Phase 5.

## Publication evidence compatibility

Existing published revision evidence is authoritative.

Phase 5 must not:

- recalculate existing revision hashes merely because Context was added;
- rewrite existing publication manifests;
- silently change already-sealed child/asset evidence.

If future publication evidence needs Context identity embedded into a manifest, that requires an explicit manifest-version upgrade with backward verification. Phase 5 should avoid such a format change unless a proof case strictly requires it.

## Routing and application compatibility

Existing Group routes remain valid:

`/groups/{group}/spaces/{space}/contents/...`

They continue to prove backward compatibility.

Introduce generic Context routes for non-Group environments, for example:

`/contexts/{context}/contents/...`

Route/model binding must verify that the requested Content, Definition and Asset belong to the same Context.

Do not expose a Context UUID as authorization. Every request still passes policy checks.

Group navigation may continue to present Content through GroupSpace tabs. Personal and Admission navigation may add focused entry points without forcing existing Group UI into a premature universal shell.

## Phase milestones

### 5A — Context identity and authorization foundation

Deliver:

- Context model + UUID + kind;
- explicit Personal/GroupSpace/Admission bindings;
- idempotent ensure Actions;
- GroupSpace backfill;
- Context policy/authorization contract;
- focused tests proving Personal, GroupSpace and pre-Membership Admission access boundaries.

No Content foreign keys move until this foundation is proven.

### 5B — Context-bind the Content substrate

Deliver:

- `context_id` compatibility migration for Content, Definitions, saved render templates and Assets;
- Group data backfill;
- same-Context invariants;
- existing Group Actions/policies adapted through the Context authorization seam;
- all existing Group Content tests remain green;
- publication evidence remains verifiable.

### 5C — Personal and Admission Content proof

Deliver:

- generic Context Content routes/application seam;
- Personal Content creation/view/edit with no Group;
- Admission candidate/reviewer Content collaboration before Membership;
- candidate remains denied ordinary GroupSpace access;
- unrelated Actors remain denied;
- terminal Admission mutation denied;
- Content Assets/annotations honor Context authorization where used by proof flows.

### 5D — closure and migration proof

Deliver:

- focused Phase 5 tests;
- full PHPUnit;
- PHPStan;
- Pint;
- Vite production build;
- fresh migrations;
- upgrade migration from accepted Phase 4 data;
- rollback limitations documented;
- browser/mobile/RTL smoke;
- durable implementation report;
- human acceptance gate.

## Required proof cases

### Existing Group Content unchanged

Given existing GroupSpace Content from the Phase 4 baseline:

- it receives a GroupSpace Context binding;
- its Definition/Assets/templates resolve to that same Context;
- existing Group routes still render and authorize identically;
- publication evidence still verifies;
- authoring/publishing/annotations still obey existing Group rules.

### Personal private Content

An active verified Actor can:

- obtain one Personal Context;
- create a context-scoped Definition;
- create/read/edit/publish Content in it;
- use Content without any Group record;
- deny every unrelated User/Actor.

### Admission Content before Membership

For an active Admission whose candidate has no Group Membership:

- candidate can enter the Admission Context;
- an authorized reviewer can enter the same Admission Context;
- candidate/reviewer can perform the Phase 5 Content collaboration proof;
- unrelated Group members/Actors are denied;
- candidate remains denied ordinary GroupSpace Content unless separately authorized by existing GroupSpace rules.

## Migration and rollback rules

- migrations are append-only;
- no `migrate:fresh` assumption;
- backfill runs deterministically from existing GroupSpace foreign keys;
- no existing Content/Definition/Asset row may be orphaned;
- no existing publication evidence is rewritten;
- compatibility columns remain until a later accepted phase proves they can be removed;
- rollback must not destroy existing Group Content data;
- if a non-Group Context has been populated, rollback limitations must be explicit rather than fabricating a GroupSpace to preserve shape.

## Exit gate

Phase 5 closes only when:

1. existing Group Content behaves unchanged;
2. Personal Content exists safely with no fake Group;
3. Admission Content is accessible to candidate/reviewer before Membership;
4. Admission Context access grants no ordinary GroupSpace authority;
5. Context authorization is server-authoritative;
6. Content/Definition/Asset same-Context integrity is proven;
7. historical publication evidence remains valid;
8. focused/full automated gates are green;
9. browser/mobile/RTL behavior is accepted by the human owner.

After that gate, Phase 6 — Content Blueprints — may begin.
