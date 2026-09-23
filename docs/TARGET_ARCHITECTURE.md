# IET Target Architecture

## Purpose

This document defines the intended technical shape of IET after the current kernels mature. It is an architectural destination, not permission to implement every subsystem immediately.

The roadmap controls execution order. Existing stable behavior should be migrated incrementally, with compatibility preserved until a phase explicitly replaces it.

## Architectural style

IET remains a modular Laravel monolith.

Use explicit bounded domains, transactional Actions, policies, events, queues, and well-defined interfaces inside one deployable application. Split services only when operational evidence demonstrates a need that cannot be solved cleanly in the monolith.

The application should optimize for:

- strong invariants;
- auditable history;
- explicit authorization;
- reusable kernels;
- purpose-specific UX;
- incremental migration;
- testability;
- observability;
- operational simplicity.

## Layer map

~~~text
┌────────────────────────────────────────────────────────────┐
│                      Domain Packs                          │
│ Learning · Project/Work · Personal · Tourism · ...        │
├────────────────────────────────────────────────────────────┤
│                      Blueprints                            │
│ Content · Interaction · Workflow · Planning · Group       │
├────────────────────────────────────────────────────────────┤
│ Experience / Application Layer                            │
│ Livewire · controllers · commands · notifications · API   │
├────────────────────────────────────────────────────────────┤
│ Reusable Domain Kernels                                   │
│ Identity · Concepts · Context · Content · Submission       │
│ Workflow · Planning · Exchange · Commitment · Accounting  │
├────────────────────────────────────────────────────────────┤
│ Infrastructure                                            │
│ DB · queue · scheduler · cache · files · mail · realtime  │
└────────────────────────────────────────────────────────────┘
~~~

## 1. Identity, Actors and acting authority

### User

User remains the authentication principal.

Owns:

- credentials;
- verified email;
- sessions;
- account status;
- locale/timezone;
- platform access grants;
- security preferences.

User is not the universal domain participant.

### Actor

Actor is the participant identity attributed in the domain.

Target kinds:

- person;
- organization;
- system.

Current one-User/one-person Actor behavior remains the compatibility starting point.

Long-term, introduce explicit acting authority such that one User can be authorized to act as multiple Actors.

Example:

~~~text
User: John
acts as:
- John Person
- John Construction Ltd
~~~

Acting authority must be explicit, auditable, revocable, and scope-aware.

Do not infer organization authority from Group Membership.

## 2. Progressive Profile

Profile is attached to Actor, not User authentication.

It contains two categories of information:

### Structured profile facts

Examples:

- display name;
- biography;
- date of birth;
- education records;
- language;
- contact preference;
- location;
- availability.

Use date of birth rather than persisted age.

### Semantic assertions

Examples:

~~~text
Actor --has_skill--> Laravel
Actor --interested_in--> Chess
Actor --wants_to_learn--> English
Actor --offers_service--> Electrical Work
~~~

Profile values require visibility/audience controls.

A Group or Admission requests specific requirements. It does not mutate a global field from optional to globally required.

## 3. Concept Kernel

Concepts provide semantic identity independently from domain state.

Rules:

- one canonical Concept may appear in multiple classification paths;
- contextual meanings such as skill, need, interest, and teaches are predicates, not duplicate Concepts;
- schemes provide classification perspectives;
- hierarchy source-of-truth is a polyhierarchical directed acyclic graph;
- closure is derived query acceleration;
- non-hierarchical semantic relations are separate from hierarchy;
- subject→Concept links are generic Assertions;
- labels/translations are representations, not identity;
- duplicate Concepts are merged/deprecated without silently rewriting historical evidence.

See `docs/CONCEPT_KERNEL.md`.

## 4. Context Kernel

Current GroupSpace is the first context-like object but Content is hard-bound to it.

Target abstraction:

~~~text
Context
├── Personal
├── GroupSpace
├── Admission
├── DirectCollaboration
├── Negotiation
├── Contract
└── Project
~~~

A Context answers:

- who can enter;
- who can view;
- who can create/interact;
- which capabilities are enabled;
- which Actor/Group owns or governs it;
- which retention/privacy policy applies.

Do not make fake one-person Groups solely to gain Content access.

### Group and Context remain different

Group answers "who participates together under shared governance?"

Context answers "within which bounded environment does this collaboration/artifact/process occur?"

A Group may own several Contexts/Spaces.

### Context-scoped collaboration

Collaboration capabilities belong to Context authorization, not automatically to Group Membership. A Context may enable Conversation, Content, Submissions, annotations, Assets, requirements, or other capabilities according to its own policy.

Admission is the canonical proof that Context access and Membership are different: an applicant may collaborate with authorized reviewers inside an Admission Context before any Membership exists, while remaining forbidden from ordinary Group participation.

Target Admission collaboration may expose separate audiences:

- shared candidate/reviewer Conversation;
- reviewer-internal Conversation;
- read-only system timeline generated from durable domain events.

Audience checks are server-authoritative. Hiding a tab or message in the UI is never sufficient authorization. Attachments inherit explicit Context/audience authorization and use the reusable Asset/evidence model rather than message-owned public blobs.

Conversation content is not domain authority. A message saying "approved", "I accept", or similar wording does not perform a transition. Approval, Agreement acceptance, Contract activation, Membership finalization, Commitments, and other authoritative facts require explicit authorized domain Actions and durable evidence.

See `docs/ADMISSION_COLLABORATION_ARCHITECTURE.md`.

## 5. Group Governance

Preserve current Group kernel:

- Membership;
- contextual role/permission;
- invitations;
- admissions;
- agreements;
- ownership integrity;
- Spaces.

Target refinements:

- versioned Group Blueprints;
- configurable admission mode;
- initial-role policy;
- context/capability provisioning;
- organization Actor participation;
- specialized UX through Domain Packs.

Groups must not become a universal container for personal/private state.

## 6. Content Kernel

Content is the universal human-facing artifact layer.

A Content object provides stable identity; revisions provide immutable editions.

Target responsibilities:

- structured fields;
- rich block composition;
- media/assets;
- appearance;
- relations/Outline;
- publication;
- semantic classification;
- annotations;
- audience rules;
- interaction definitions;
- immutable publication evidence.

Examples:

- post;
- article;
- forum topic;
- book;
- lesson;
- page;
- project report;
- diary entry;
- proposal;
- agreement document;
- job posting;
- questionnaire;
- exam;
- travel guide.

### Evidence-addressable Content

When Content is used as evidence, references bind an exact immutable published revision and may additionally target an exact field, block UUID, asset placement UUID or relationship UUID. Evidence/reputation layers must not point only to mutable current Content when historical truth matters.

Skill self-ratings, evidence maturity, verification and reputation are separate dimensions. Artifact count alone must not silently rewrite an Actor's self-reported proficiency.

### Content is not universal domain state

A Job Opportunity may have Content, but structured Need/requirements remain a domain object.

A Contract may have Content, but parties/commitments/signatures are domain objects.

An Invoice may render through Content, but accounting records remain ledger objects.

### Content Context migration

Long-term naming:

~~~text
SpaceContent               → Content
SpaceContentRevision       → ContentRevision
SpaceContentDefinition     → ContentDefinition
...
~~~

Do not perform a cosmetic mass rename before ContentContext exists. Rename only at a migration boundary where the old Space-only assumption is truly removed.

## 7. Content Blueprints

RenderTemplate remains appearance only.

Introduce versioned ContentBlueprint above it.

A Blueprint version may define:

- Content Definition/schema defaults;
- initial Blocks;
- RenderTemplate and safe token defaults;
- Concept classifications;
- interaction policy;
- authoring capability hints;
- optional future Submission schema;
- optional future Workflow;
- audience defaults.

Blueprints are recipes over the one Content kernel, not parallel Content types. Personal, GroupSpace, Admission and later Contexts should converge on the same Studio/Reader capabilities with progressive Quick → Guided → Advanced authoring.

Examples:

- Article;
- Social Post;
- Book;
- Lesson;
- Workbook Page;
- Questionnaire;
- Exam;
- Project Report;
- Job Posting.

Blueprint changes never silently mutate existing published Content.

## 8. Submission / Response / Evaluation

Annotations answer:

> "What do I want to say about this target?"

Submissions answer:

> "What structured response am I giving to this interaction?"

Target structure:

~~~text
InteractionDefinition
       ↓
Submission
       ↓
Response
       ↓
Review / Evaluation
~~~

Use for:

- employment application;
- exam;
- assignment;
- questionnaire;
- survey;
- inspection;
- application form;
- supplier quotation.

Submissions bind to an exact published Content/Interaction version so later author edits cannot change what the respondent answered.

## 9. Workflow Kernel

Workflow provides configurable process structure where reuse is proven.

Target concepts:

- WorkflowDefinition;
- WorkflowVersion;
- State;
- Transition;
- Requirement;
- transition authorization;
- evidence requirement;
- transition event/history.

Do not replace domain-specific invariants with a generic workflow interpreter.

The domain object remains authoritative and uses the Workflow kernel only for reusable transition structure where appropriate.

Admission and another unrelated domain must prove the engine before it is treated as generic.

## 10. Planning Kernel

Target:

~~~text
Plan
ScheduleRule
Occurrence
Participant
Completion
Evidence
~~~

Must support:

- one-time activity;
- recurrence;
- timezone correctness;
- personal activity;
- Group activity;
- assignments/participants;
- due windows;
- completion/skipping/cancellation;
- actual start/end timestamps where execution is tracked;
- evidence;
- reminders;
- explicit optional source/provenance links so an Occurrence may later be materialized from a Contract/Commitment without making Planner itself the source of obligation truth.

Proof cases:

- nightly chess-book study;
- weekly class;
- construction worker shift.

Content may describe a Plan; it does not replace scheduling data.

## 11. Need / Offer / Exchange

Target domain:

~~~text
Need
Offer
Match
Proposal
Negotiation
Agreement
Commitment
Fulfillment
~~~

A Need/Offer can refer to:

- Concept;
- quantity/unit;
- quality/constraints;
- time window;
- location/context;
- price/terms;
- required evidence.

Matching is advisory.

A match never creates an obligation.

Only explicit agreement/commitment transitions establish obligations.

Need/Offer/Matching is optional discovery. Parties who already know each other may create a Proposal/Negotiation/Contract directly; the obligation kernel must never require a Match record.

## 12. Agreements, Contracts and Commitments

Keep distinct layers:

### Group Agreement

Rules/terms applying to participation in a Group.

Group Agreement versions are immutable evidence. Discussion or annotations may motivate a change, but an active version is never edited in place. A change creates a new draft/proposed version, passes the authorized governance/approval path, and receives explicit activation/effective timing. The prior version remains historical evidence and is superseded only according to the lifecycle. `reacceptance_required` or equivalent policy determines whether existing members must accept the new exact version.

One candidate/reviewer discussion must never silently rewrite Group-wide terms. It may propose a future Group Agreement version, but only authorized Group governance can approve/activate that version.

### Negotiated Agreement / Contract

Terms between explicit parties, potentially with individual commitments. Candidate-specific negotiated terms belong here rather than in the Group Agreement merely because negotiation occurred during Admission.

Each proposed Contract version binds exact immutable terms, eventually referencing a sealed Content revision. Required parties explicitly accept the exact version. Activation occurs only when the Contract's authorization/acceptance conditions are satisfied. Later amendments create new versions; previously accepted versions are never mutated.

Conversation text is negotiation evidence, not acceptance evidence. A statement such as "I agree" in chat has no authoritative effect unless the Actor performs the explicit acceptance Action for the exact version.

### Commitment

A concrete obligation such as:

- perform work;
- deliver a good;
- attend;
- provide a service;
- pay;
- make a resource available.

### Fulfillment

Evidence that a Commitment was performed wholly or partly.

For time-based work, Fulfillment may bind a concrete Planner Occurrence and record facts such as actual start/end, quantity/duration, completion status, notes and exact Content/Asset evidence. Review/acceptance/rejection/clarification remains explicit domain state; a chat message or annotation may explain a decision but does not itself perform it.

A Contract may create or govern multiple Commitments, including paired obligations such as:

~~~text
Worker Commitment: perform agreed work
Counterparty Commitment: pay agreed compensation for accepted fulfillment
~~~

Amendments create a new Contract version with explicit effective timing. Future Commitments/Occurrences use the new effective version; historical work, fulfillment, accepted evidence and earned obligations remain bound to the version that governed them.

Human-readable terms should eventually reference exact sealed Content revisions, while operational state remains in Agreement/Contract models. Agreement/Contract acceptance evidence records exact version/content identity, party/Actor, acting User/authority, time, and integrity hash as appropriate.

## 13. Accounting Kernel

Production accounting is deliberately conservative.

Target:

~~~text
Ledger
Account
JournalEntry
JournalLine
MonetaryUnit
ExchangeRate
Balance cache / snapshot
~~~

Rules:

- journal posting is always atomic;
- posted entries are immutable;
- corrections use reversal/correcting entries;
- debit totals equal credit totals;
- idempotency is enforced at source boundaries;
- source domain records explain why an entry exists;
- acting Actor and User provenance are preserved;
- financial history is never cascade-deleted;
- cached balance is not source of truth.

See `docs/FINANCIAL_ARCHITECTURE.md`.

### Cross-kernel composition proof — direct paid work

The architecture must support this connected chain without duplicating truth:

~~~text
Actors
  ↓
Negotiation Context + Conversation
  ↓
accepted ContractVersion
  ↓
Commitments
  ├── perform work
  └── pay compensation
  ↓
Plan / ScheduleRule
  ↓
Occurrence(s)
  ↓
Fulfillment
  ├── actual start/end
  ├── status/quantity
  └── Content / Asset evidence
  ↓ explicit review / acceptance
Financial Obligation
  ↓
Ledger posting
  ↓
Settlement / Payment
~~~

The user-facing system must be able to derive **scheduled, worked, accepted, earned, paid, outstanding and disputed** amounts/statuses from those linked records. “Amount owed” is not a manually maintained magic counter; it is derived from authoritative obligation/accounting state. Conversation and Content provide context/evidence around this chain but do not replace its domain facts.

The same primitives must also support personal plans that have no Contract, unpaid commitments, non-time-based deliverables, and organization-to-person relationships.

## 14. Financial Laboratory

The laboratory is separate from production accounting.

Target instrument model:

~~~text
FinancialInstrument
├── InstrumentVersion
├── IssuancePolicy
├── TransferPolicy
├── ValuationPolicy
├── RedemptionPolicy
├── ReservePolicy
└── DistributionPolicy
~~~

Target event/evidence:

- issuance;
- burn;
- transfer;
- valuation snapshot;
- distribution;
- simulated redemption;
- reserve snapshot;
- operational-cycle metrics.

Instrument kinds may include:

- internal credit;
- loyalty point;
- voucher;
- group unit;
- commodity-indexed unit;
- redeemable claim;
- profit-participation experimental unit.

A Group-created "coin" is not automatically a currency.

Experimental valuation formulas must be versioned, constrained, reproducible, and supplied with immutable input snapshots.

## 15. Controlled external money

Real bank/card/provider movement is outside the free-form laboratory.

Production external-money integration requires:

- approved instrument/use case;
- payment provider/banking integration;
- reconciliation;
- payout request lifecycle;
- reserve model if applicable;
- fraud/abuse controls;
- jurisdiction-specific legal/compliance review;
- audit trail;
- operational limits.

Never promise cash redemption merely because an internal instrument has a displayed reference value.

## 16. Real-time architecture

Use domain events and post-commit delivery.

Target:

~~~text
Domain Action
→ transaction commits
→ durable event/outbox
→ queue
→ authorized broadcast/notification
→ client update
~~~

Use real-time delivery for:

- chat;
- replies;
- annotations;
- reactions;
- submission status;
- workflow events;
- planning events;
- notifications;
- presence where useful.

Rules:

- no authorization decision trusts the browser;
- every subscription/channel is authorized;
- client reload reconstructs state from DB;
- events are idempotently consumable where required;
- broadcast failure never rolls back already-committed domain truth unless the domain explicitly requires synchronous delivery;
- message delivery never becomes a hidden domain transition mechanism: authoritative actions commit first, then durable events/system messages/broadcasts describe the result;
- attachments and annotations remain authorized through their Context/audience after reconnect or reload, not merely through possession of a broadcast payload.

## 17. Blueprints and Domain Packs

### Blueprint hierarchy

- ContentBlueprint;
- InteractionBlueprint;
- WorkflowBlueprint;
- PlanningBlueprint;
- GroupBlueprint.

Blueprints are versioned.

Instantiated objects remember their source Blueprint version.

Blueprint upgrades are explicit and previewable; never silently mutate existing Groups/Content.

### Domain Packs

Domain Pack = curated Blueprints + capabilities + terminology + UI.

Initial target packs:

- Learning;
- Project / Work;
- Personal.

Later:

- Tourism;
- Marketplace/Services;
- other proven domains.

Domain Packs should reuse kernels rather than create parallel backend systems.

## 18. Home / discovery experience

Mature Home should answer:

- what needs my attention?
- what am I doing today?
- who is waiting on me?
- what Groups am I part of?
- what do I want to continue learning?
- which commitments/obligations are due?
- which opportunities match my explicit profile?
- what changed?

Modules are progressively disclosed. Users do not see unused complexity.

Recommendations use explicit Profile/Concept data and should be explainable and controllable.

## 19. Cross-cutting production requirements

Every domain phase must respect:

### Security

- least privilege;
- route/policy/action authorization;
- CSRF/session security;
- rate limiting;
- upload validation;
- private-by-default sensitive data;
- audit trails;
- dependency/security updates.

### Privacy

- purpose-aware data collection;
- field/assertion visibility;
- export;
- deletion/retention policy;
- consent where required;
- no advertiser access to private user conversations/data in product design assumptions.

### Reliability

- transactions;
- idempotency;
- queues;
- retry semantics;
- dead-letter/failed-job operations;
- backups;
- restore drills;
- migration rollback/forward plan;
- monitoring.

### UX

- responsive/mobile;
- accessible keyboard/focus behavior;
- contrast checks;
- progressive disclosure;
- clear empty states;
- explicit destructive confirmations;
- dedicated complex workflow pages instead of giant modals.

### Performance

- pagination;
- indexing;
- bounded graph traversal;
- query budgets;
- caching only after correctness;
- async processing for expensive media/search/recommendation work.

## 20. Dependency rule

The target architecture is intentionally broader than any one release.

Implementation order is authoritative only in `docs/PRODUCTION_ROADMAP.md`.

Agents must not jump from this document directly to coding a later subsystem.
