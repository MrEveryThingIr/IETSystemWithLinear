# Capability Mesh Architecture

## Purpose

IET should behave as a set of trustworthy, independently useful capability nodes that a human can compose when needed.

The product must not force every simple activity through one giant workflow.

A user may stop at:

~~~text
simple Plan
~~~

or explicitly extend it later:

~~~text
Plan
+ participants
+ evidence
+ financial calculation
+ Contract / Agreement
+ Fulfillment
+ Financial Obligation
+ Settlement
+ Accounting posting
+ automation
~~~

Each added capability is optional unless a chosen domain rule genuinely requires it.

## Core principle — independent nodes, explicit composition

A capability node:

- is useful on its own;
- owns only its own authoritative state;
- exposes explicit Actions and references;
- can be invoked from multiple relevant views;
- may project read-only summaries into shared surfaces;
- can be connected to another node by an explicit user/domain Action;
- can be omitted when unnecessary.

A node must not acquire another node's authority merely because both appear on the same page.

Examples:

- Planner owns schedules and occurrences, not Contracts or money.
- Personal Accounting owns ledger truth, not contractual obligation truth.
- Contract owns accepted party-specific terms, not calendar rendering or journal balances.
- Group Agreement owns Group participation rules, not negotiated private Contract terms.
- Calendar owns no business event truth; it projects authorized temporal facts from providers.
- Ambient header owns no domain truth; it renders selected projections and live temporal context.
- Content/Evidence stores human artifacts and exact references, not hidden approval/payment/workflow state.

## Runtime composition

The runtime model is:

~~~text
current view / current object / current Context
        ↓
capability discovery
        ↓
only authorized + relevant capability actions are offered
        ↓
human chooses an action
        ↓
explicit domain Action
        ↓
source node creates/links authoritative state
        ↓
provenance/reference is recorded
        ↓
other nodes may project/read it through authorized adapters
~~~

Examples:

~~~text
Plan page
→ Add financial tracking
→ create/link Accounting workspace or economic projection
~~~

~~~text
Plan occurrence
→ Attach evidence
→ exact Content/Asset reference
~~~

~~~text
Contract
→ Schedule commitment
→ explicit Commitment/Planner binding
~~~

~~~text
Calendar slot
→ Add
   ├─ Plan
   ├─ Reminder
   ├─ Note / Content
   └─ other authorized temporal capability
~~~

~~~text
Need / Offer
→ start Relationship
→ optionally negotiate Proposal
→ optionally create Contract
~~~

The system should never require all later steps simply because they are available.

## Capability contract

Every reviewed node documents:

1. Authority — state it owns.
2. Standalone value — what works without optional modules.
3. Inputs — references/data it may accept.
4. Outputs — references/events/projections it exposes.
5. Actions — explicit mutations users/other domains may request.
6. Policies — who can discover/use each action.
7. Temporal projection — if/how records appear in Calendar/Today/header.
8. Financial projection — if/how values may be calculated or posted.
9. Evidence seam — exact Content/Asset references accepted/emitted.
10. Automation seam — actions allowed for explicitly activated automation.
11. Idempotency/concurrency rules.
12. What is not implied by linking this node.

## Capability discovery in the UI

A relevant view may expose a consistent capability launcher such as:

~~~text
Add / Connect / Use
~~~

Choices are derived from current object/domain, Context, viewer policies, lifecycle state, accepted capability providers and explicit Blueprint/domain hints.

The launcher must not infer authority from labels or free text.

Examples:

- a Plan may offer Evidence, Participants, Finance, Contract linkage;
- a Contract may offer Commitments, Planner scheduling, Evidence;
- a Content item may offer citation/evidence, interaction/submission, placement;
- a Relationship may offer Conversation, Proposal, Contract, Planner;
- a GroupSpace may offer Content, Planner, Conversation, Submission review;
- a Calendar slot may offer any authorized temporal creation capability.

## Wiring primitives

Prefer a small set of explicit composition primitives rather than bespoke hidden coupling.

### Exact reference

One node refers to immutable/durable identity in another node.

Examples: Content revision/block/asset evidence, Plan occurrence, Contract version, Financial obligation.

### Provenance link

Records why a new object exists.

Examples:

- Relationship created from Need/Offer;
- Contract created from Proposal;
- Commitment created from ContractVersion;
- Plan created/bound from Commitment;
- JournalEntry posted from an accepted economic event.

### Explicit Action

All authoritative cross-node mutation happens through a named Action/service, never because a view was rendered.

### Domain event / outbox

After authoritative commit, durable events may notify/project to other systems.

An event describes committed truth; it is not a hidden command unless an explicitly configured automation consumes it.

### Projection provider

A node may expose authorized read-only projections to Calendar, Home/Today, ambient header, search or dashboards.

Projection never becomes source truth.

## Shared Temporal Fabric

Time is cross-cutting infrastructure, not owned by Planner.

### Temporal kernel

The shared temporal service controls:

- canonical stored instants;
- profile timezone;
- profile calendar system;
- locale-aware formatting/parsing;
- Gregorian / Persian (Jalali) / supported Hijri presentation;
- secondary Gregorian equivalence when the primary calendar is non-Gregorian;
- DST/boundary behavior;
- date/datetime input components;
- consistency across all modules.

The later candidate implementation already contains substantial proven work:

- App/Support/TemporalCalendar.php;
- profile-aware local date/time components;
- profile-aware datetime inputs;
- cross-system temporal consistency tests;
- JS temporal formatting/input support.

These are recovery candidates to inspect and re-admit early.

### Fractal Calendar

The fractal calendar is a shared temporal navigation and projection surface.

It should support drill-down such as:

~~~text
years
→ year
→ month
→ day
→ hour
→ 60 / 30 / 15 / 5 / 1 minute partitions
~~~

Each cell/partition may show authorized temporal projections from independent providers.

Potential providers include:

- Planner occurrences;
- reminders;
- Contract effective/expiry/review dates;
- Group Agreement activation/effective dates;
- Financial obligations/due dates;
- Settlement/payment events;
- scheduled accounting automations;
- Proposal/Relationship deadlines;
- Admission/review windows;
- Content publication/review dates;
- Submission/Evaluation due items;
- notifications/attention items;
- notes/annotations intentionally carrying temporal placement.

The calendar stores no duplicate copy of those domain records. Every projected item links back to its authoritative source.

### Calendar creation/actions

From a time cell, the user may invoke applicable capability Actions with the selected time window prefilled.

Examples:

~~~text
selected 2026-10-01 10:00–11:00
→ Create Plan
→ Add Reminder
→ Add Note
→ Schedule Commitment
→ Schedule Contract review
→ schedule an explicitly supported financial action
~~~

Only capabilities authorized for the current user/Context are offered.

## Ambient Capability Rail

The permanent additional header bar is a shared shell surface.

The already-tested candidate includes an ambient-status component with:

- live profile-aware date/time;
- secondary Gregorian equivalent where applicable;
- rotating ambient text.

The target is broader but still non-authoritative: a user-configurable rail of small projections/widgets such as:

- local live clock/date;
- equivalent Gregorian time/date;
- timezone;
- current/running Plan;
- next Plan/reminder;
- waiting-on-me count;
- unread notifications;
- financial due indicator;
- rotating personal/system prompts;
- Context-specific status.

Rules:

- each item comes from an authorized provider;
- user chooses what is shown and ordering where practical;
- the rail does not mutate domain truth just by rendering;
- actionable widgets invoke explicit domain Actions/routes.

## Planner as an independent node

Planner must be fully useful for:

- one simple personal plan;
- one-time or recurring scheduling;
- participants when desired;
- reminders;
- actual start/end;
- completion/cancel/skip;
- evidence when desired.

It must not require Contract, finance, Group, Relationship or Need/Offer.

## Finance as an independent node

Personal Accounting must be useful independently for opening balance, expense, income, transfer, correction/reversal and reports per MonetaryUnit.

It may optionally consume explicit economic events from Contracts/Fulfillment/Settlement.

A Plan with a cost estimate does not automatically post accounting truth.

## Agreement / Contract independence

Group Agreement and negotiated Contract remain distinct authorities.

A Group Agreement can work without Planner or Personal Accounting.

A Contract can work without Planner if no schedule is needed.

Optional links may add Commitments, Planner scheduling, evidence, economic obligations, settlement and accounting.

## Needs / Offers / Relationships

Need/Offer discovery and Relationship coordination are optional entry points.

A user who already knows the counterparty can directly create a Relationship/Proposal/Contract where policy permits.

Matching must never be mandatory for business composition.

## Automation

Automation is a separate capability, not a hidden property of every node.

Target:

~~~text
explicit automation definition
→ user reviews scope/trigger/action
→ explicit activation
→ scheduler/event trigger
→ authorized Action
→ idempotent execution
→ audit/result
→ pause/deactivate
~~~

Potential examples include recurring Planner materialization, reminders, scheduled financial obligations and prepared/scheduled transactions where permitted.

Automation must never infer consent to accept Contracts, approve reviews, move external money or post accounting truth merely from a schedule unless the exact capability explicitly defines and authorizes that behavior.

## Composition examples

### Basic personal plan

~~~text
Plan
→ recurrence
→ reminder
→ completion
~~~

### Plan with personal finance

~~~text
Plan
→ optional budget/expected cost reference
→ explicit expense/income posting when it actually happens
→ Accounting
~~~

### Paid work

~~~text
Relationship (optional if parties already have direct authority)
→ Proposal (optional)
→ Contract
→ Commitment
→ Planner
→ Fulfillment
→ Financial Obligation
→ Settlement
→ explicit Accounting posting
~~~

Each arrow is an explicit seam, not an automatic implication.

### Group participation

~~~text
Invitation
→ Admission
→ required Group Agreement acceptance
→ Membership
→ optional GroupSpace capabilities
   ├─ Content
   ├─ Planner
   ├─ Conversation
   └─ Submission/Evaluation
~~~

### Calendar-centric composition

~~~text
Fractal Calendar
→ authorized temporal projections from many nodes
→ user drills to a time partition
→ user chooses Add / Connect / Open
→ explicit source-domain Action
~~~

## Anti-patterns

Do not introduce:

- one universal everything table;
- one generic JSON workflow that owns all domain rules;
- hidden cross-module writes from Blade/rendering;
- automatic Contract acceptance from chat/content wording;
- automatic ledger posting from Plan completion;
- Calendar rows duplicating source-domain truth;
- Group-specific copies of Planner/Content/Accounting;
- forced Need/Offer matching before direct collaboration;
- a giant wizard requiring every optional layer up front.

## Review philosophy

The reassembly reviews nodes and seams separately.

First prove a node is independently good.

Then prove one explicit connection at a time.

This keeps composition reversible, understandable, testable and human-directed.
