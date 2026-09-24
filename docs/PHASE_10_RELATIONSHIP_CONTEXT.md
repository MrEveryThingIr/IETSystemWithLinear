# Phase 10 — Relationship + Relationship Context

## Status

Remote runtime implementation complete and green on feat/ideal-v1-10-relationship-context.

Runtime checkpoint:

~~~text
SHA: 6cb21465489c14efffc589c41070f46160e029c2
GitHub Actions: 36025406749
439 tests / 2481 assertions
PHPStan: clean
Vite/migrations/rollback-reapply/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Kernel checkpoint:

~~~text
SHA: aab172e787473f36100f5eb6d20cf217862de630
GitHub Actions: 36024079779
436 tests / 2460 assertions
~~~

Baseline: 71ffb7af0d2bda56af0df5e3aba2a0377fee8afc on integration/ideal-v1.

## Purpose

Represent meaningful direct Actor-to-Actor coordination without manufacturing a Group Membership, Contract, obligation, ownership claim or payment consequence.

A Relationship is a durable coordination boundary with explicit participants, roles, purpose and lifecycle. It owns a dedicated Context so existing Content and interaction capabilities can compose there under Relationship-specific authorization.

## Domain shape

~~~text
Relationship
├── purpose Concept
├── optional originating ActorProfileIntent
├── creator Actor
├── RelationshipParticipant[]
│   ├── Actor
│   ├── human role
│   ├── participation lifecycle
│   └── can_manage
├── RelationshipContext ──→ Context(kind=relationship)
└── RelationshipEvent[]  (immutable lifecycle provenance)
~~~

The implementation deliberately avoids a giant relationship-type enum. Purpose is semantic Concept data; participant roles are explicit; later capability composition is selected by purpose and product flow rather than combinatorial types.

## Consent and lifecycle

Relationship lifecycle:

~~~text
proposed
├── all invited participants explicitly accept → active
└── participant declines / proposer cancels → cancelled

active
└── authorized manager explicitly ends → ended
~~~

Participant lifecycle:

~~~text
invited → active
       └→ declined

active → left   (kernel state reserved; no ordinary leave UI yet)
~~~

Creating a Relationship creates a **proposal**, not active collaboration. The creator is an active managing participant; invited participants remain invited. The Relationship Context is visible to participants so they can inspect the request, but it remains non-writable until the Relationship becomes active.

## Relationship Context authorization

Relationship Context authorization is derived from explicit Relationship participation, not Group Membership.

- any recorded participant may view the Relationship and its Context history;
- invited participants may inspect the proposed boundary and respond;
- proposed Context is read-only;
- after activation, active participants may create/interact with Content;
- can_manage participants may manage Content/definitions/review capability;
- ended/cancelled Context remains readable but becomes read-only;
- outsiders receive no Relationship or Context access;
- no Group Membership is created or inferred.

This allows Alice and Bob to coordinate directly even if neither needs to join Maple Housing Office for that relationship.

## Originating Intent

A visible active Intent may seed a Relationship.

The Relationship stores originating_intent_id as durable provenance and requires the Relationship purpose Concept to match the canonical Concept of that Intent. The Intent owner must be one of the Relationship participants.

This link means “this coordination began from that expressed Need/Offer.” It does **not** mean the Intent became a Match, Proposal, Contract, employment agreement, financing instrument or obligation.

## User experience

Implemented routes:

~~~text
/relationships
/relationships/create
/relationships/{relationship}
~~~

### From discovery

1. Bob opens **Needs, offers & services**.
2. Bob sees Alice's visible construction-service Need.
3. Bob chooses **Start relationship**.
4. Purpose is inherited from the Intent; Alice is fixed as the participant.
5. Bob states the human roles, for example service provider and client, and may add a label such as Riverside electrical work.
6. Bob sends the request.
7. Alice opens Relationships and sees the pending request.
8. Alice explicitly accepts.
9. The Relationship becomes active and its Context becomes writable.

### Direct request

1. Alice opens **Relationships → Start relationship**.
2. Alice selects the purpose Concept.
3. Alice enters the exact username of the known participant, for example Carol.
4. Alice records both roles, for example project owner / capital collaborator.
5. Alice sends the request.
6. Carol must explicitly accept before active collaboration begins.

The detail page shows purpose, participant roles/states, origin Intent when present, immutable lifecycle events and the Relationship workspace.

## Composition with existing Content

The Relationship Context immediately uses the existing Context/Content kernel.

When active:

- participants can create ordinary Content from compatible Blueprints;
- a manager can manage Content/definitions according to ContextPolicy;
- published Content can later be presented there through existing placement rules when the actor independently has the required source-read and target-management authority.

No relationship_posts, duplicate Article/Album tables or parallel authoring engine were introduced.

Conversation/Timeline is Phase 11 and remains separate from current Content behavior.

## Story proof

### Alice ↔ Bob

Alice has a visible Need for Riverside construction/electrical work. Bob opens that Intent and starts a Relationship:

~~~text
Purpose: residential construction / electrical work
Bob role: service provider
Alice role: client
State: proposed
~~~

Before Alice accepts, both may inspect the request but the Relationship Context is read-only.

Alice accepts explicitly:

~~~text
Relationship → active
Relationship Context → writable for active participants
Group Membership → unchanged / absent
Contract → absent
Payment obligation → absent
~~~

### Alice ↔ Carol

Alice can also start a separate direct Relationship with Carol for the capital/collaboration Concept. It is a separate coordination boundary with its own participant roles, lifecycle and Context. Mentioning investment or ownership never grants either one; later Proposal/Contract phases must express authoritative terms explicitly.

## Negative guarantees

Phase 10 intentionally does **not**:

- create or require fake Groups;
- create Group Membership;
- infer a Match from complementary Needs/Offers;
- make chat/text/Content authoritative acceptance;
- create Proposal or negotiated terms;
- create Contract or ContractVersion;
- create employment, ownership, loan/equity or capital rights;
- create Commitment, Fulfillment or payment obligation;
- move authoritative finance/accounting facts into Relationship metadata;
- reintroduce AI runtime.

## Remote validation

GitHub Actions run 36025406749 on 6cb21465489c14efffc589c41070f46160e029c2 proves:

- **439 passed / 2481 assertions**;
- changed-file Pint: passed;
- PHPStan: no errors;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler/database-queue smoke: passed with no failed jobs;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused relationship coverage proves:

- explicit acceptance is required before the Context becomes writable;
- activation is not Group Membership;
- outsiders cannot view Relationship or Context;
- ended/cancelled Context is preserved read-only;
- decline cancels the proposal;
- visible active Intent provenance is retained without Contract implication;
- Intent-directory handoff creates the expected pending Relationship;
- direct known-username creation works;
- route and workspace authorization are enforced.

## Defects corrected during implementation

Remote gates caught and corrected without weakening tests:

1. one PHPDoc alignment issue detected by Pint;
2. an over-specific invitee PHPDoc shape that made PHPStan treat deliberate runtime validation as unreachable;
3. missing imports in the global model-factory coverage test.

## Known limitations / deferred work

- the current creation UI invites one counterpart at a time, although the kernel supports multiple initial invitees;
- adding/removing participants after creation and ordinary participant leave UI are deferred;
- direct creation uses an exact known username rather than broad people discovery;
- Conversation/messages and unified Timeline are Phase 11;
- Planner, Proposal, Contract, Fulfillment and accounting remain later specialized kernels;
- richer purpose-driven capability discovery/presentation is progressive future work;
- local/browser acceptance remains deferred to docs/LOCAL_ACCEPTANCE_WORKSHEET.md.

## Exit gate

Phase 10 exits when:

- direct Relationships exist outside Group Membership;
- participants, roles, purpose and lifecycle are durable;
- consent gates writable collaboration;
- each Relationship owns a dedicated authorization-safe Context;
- originating Intent provenance is explicit;
- normal UI supports both Intent-origin and direct request proof paths;
- no later authoritative domain consequence is implied;
- canonical docs/System Manual/acceptance worksheet are synchronized;
- full remote CI is green.
