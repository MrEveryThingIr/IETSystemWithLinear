# Selective Assembly Roadmap — Capability Mesh Revision

## Mission

Re-create the first publishable Ideal-v1 by selectively re-admitting and revising already-developed capability nodes.

This is not a rebuild from scratch and it is no longer a linear feature pipeline.

The target product is a capability mesh:

- each module/layer is independently useful as far as its meaning allows;
- a user may keep a flow simple;
- additional capabilities can be invoked later from relevant views;
- wiring is explicit, authorized and reversible;
- shared surfaces such as Calendar, Home/Today and the permanent header project information from many nodes but do not own their truth.

See docs/CAPABILITY_MESH_ARCHITECTURE.md.

## Browser finding that triggered this revision

The owner tested codex/ideal-v1-selective-assembly after M00 remote certification and found that it correctly resembled the older accepted baseline, but several already-implemented and previously browser-tested improvements were absent because they live on later candidate branches.

Important missing candidate capabilities include:

1. profile-aware date/time/calendar presentation throughout the application;
2. Gregorian equivalence when Persian/Hijri is selected;
3. live date/time in a permanent app-shell header/status bar;
4. rotating ambient header text;
5. profile-aware date/datetime inputs;
6. fractal calendar drill-down to minute partitions;
7. calendar projections and creation prefill;
8. Planner execution-window/evidence hardening.

Relevant source branches include:

~~~text
codex/release-first-publication-hardening
integration/ideal-v1-planner-temporal-candidate
integration/ideal-v1-temporal-calendar-reconcile
fix/planner-execution-window-calendar-evidence
fix/planner-temporal-evidence-calendar-hardening
~~~

These branches remain source libraries, not wholesale merge units.

## M00 result

M00 proved that the selected foundation is remotely healthy.

It did not prove that this old baseline is the desired product experience.

Owner browser result:

~~~text
technical baseline: healthy
runtime appearance: as expected for old baseline
product acceptance: NOT CLOSED
reason: previously tested cross-cutting capabilities are absent from this baseline
~~~

Therefore the next work is not M01 registration.

The next work is Foundation Recovery F1.

## Reassembly method

For every node or shared capability:

~~~text
inventory accepted behavior
→ inventory later candidate implementations
→ preserve living pre-plan
→ choose the strongest coherent behavior
→ refactor boundaries where needed
→ implement/recover selectively
→ focused + full remote validation
→ owner browser review
→ correction loop
→ accept node
→ then review optional seams to other accepted nodes
~~~

A node can be accepted independently even if many optional integrations are not yet wired.

A seam is reviewed separately from the nodes it connects.

## Two kinds of work

### Node review

Prove the capability itself is good.

Examples: Planner, Personal Accounting, Content, Group Admission, Contract, Need/Offer.

### Seam review

Prove two nodes cooperate correctly.

Examples: Contract → Commitment, Commitment → Planner, Fulfillment → Financial Obligation, Settlement → Accounting, any temporal domain → Fractal Calendar, any relevant view → Capability Launcher.

Do not hide seam behavior inside either node.

## Cross-cutting foundation recovery — first priority

### F1A — Shared Temporal Kernel

Recover/review the later profile-aware temporal implementation before reviewing time-bearing nodes.

Target:

- profile timezone throughout;
- profile calendar system throughout;
- Gregorian / Persian (Jalali) / supported Hijri presentation;
- Gregorian equivalent below/alongside non-Gregorian primary rendering;
- date/datetime input consistency;
- local-time formatting across Planner, Contracts, Agreements, Finance, Content, Admissions, Notifications, Today and other views;
- DST/boundary correctness;
- tests preventing raw Gregorian/profile mismatches.

Primary candidate source:

~~~text
codex/release-first-publication-hardening
~~~

Known candidate artifacts include App/Support/TemporalCalendar.php, resources/js/temporal.js, local date/time components, profile-aware datetime input and TemporalPresentationConsistencyTest.

### F1B — Ambient App-Shell Capability Rail

Recover/review the already-tested permanent header status component.

Initial accepted scope:

- live profile-aware date/time;
- Gregorian equivalent for non-Gregorian primary calendar;
- timezone-aware display;
- rotating ambient text;
- permanently available in the shared app shell.

Second-review target:

make it a customizable ambient capability rail where later accepted modules may contribute optional projections such as current/next Plan, reminders, waiting-on-me, notifications and financial due indicators.

The rail remains projection-first and never owns domain truth.

Primary candidate artifact:

~~~text
resources/views/components/app/ambient-status.blade.php
~~~

### F1C — Shared Fractal Calendar Fabric

Recover/review the fractal calendar early, but remove conceptual ownership from Planner.

The Calendar becomes a shared temporal projection/navigation surface.

Target drill-down:

~~~text
years
→ year
→ month
→ day
→ hour
→ 60 / 30 / 15 / 5 / 1 minute partitions
~~~

Initial candidate already demonstrates year/month/day/hour navigation, selectable minute quantum, Planner occurrence projection, create-plan prefill and profile-aware calendar navigation.

Second-review target:

define provider adapters so later nodes can optionally project authorized items such as Plans, reminders, Contract dates, Group Agreement dates, financial due/settlement events, scheduled accounting automation, Admission/review deadlines, Content publication/review dates, Submission/Evaluation due items and intentional temporal notes/annotations.

The Calendar stores no duplicate authoritative copy.

### F1D — Capability Launcher / Composition Contract

Before wiring domain nodes together, establish the UI/architecture contract for:

~~~text
Add / Connect / Use
~~~

A relevant view discovers only authorized, applicable capabilities.

This does not require one universal database table. It requires a stable application contract/registry for capability providers and explicit domain Actions.

## Capability-node review pool

After F1 is accepted, nodes are reviewed primarily by priority and user value, not because every earlier item is a runtime prerequisite.

### N1 — Planner

Standalone target:

- simple Plan first;
- one-time/recurring;
- execution windows;
- waiting/running/completed/cancelled/skipped;
- actual start/end;
- reminders;
- participants optional;
- evidence optional;
- independent list/today/calendar projections.

No finance/Contract/Group is required.

### N2 — Personal Accounting / Finance

Standalone target:

- MonetaryUnit;
- default monetary unit preference;
- opening balance;
- expense;
- income;
- transfer;
- correction/reversal;
- per-unit summaries.

Optional seams are reviewed later.

### N3 — Agreement / Contract authority

Review separately inside the authority family:

- Group Agreement;
- Proposal/negotiation;
- negotiated Contract;
- immutable versions;
- explicit acceptance/effective timing.

No Planner or Accounting requirement for basic validity.

### N4 — Need / Offer / Relationship

Standalone discovery/coordination:

- Need/Offer;
- matching;
- direct Relationship;
- Conversation/Timeline.

Matching remains optional. Known parties can start direct coordination where authorized.

### N5 — Group / Invitation / Admission / Membership

Standalone organization/governance node.

Optional GroupSpace capabilities are attached rather than copied.

### N6 — Content / Assets / Evidence

Standalone artifact and evidence node.

Other nodes reference exact artifacts/revisions/blocks/assets.

### N7 — Submission / Evaluation

Standalone structured response/review node.

### N8 — Notifications / Realtime / Home-Today

Projection/attention node over accepted capabilities.

## Seam review pool

Seams begin only after both endpoint nodes are independently accepted and are reviewed in small reversible units.

### S-PF — Planner ↔ Finance

- attach expected budget/cost without posting;
- post actual expense/income explicitly;
- financial summary projection on Plan;
- no Plan completion → automatic ledger write.

### S-PC — Planner ↔ Contract/Commitment

- schedule a Commitment;
- materialize occurrences;
- preserve ContractVersion provenance;
- schedule change does not rewrite Contract terms.

### S-CE — Contract/Fulfillment ↔ Economic Obligation

~~~text
accepted ContractVersion
→ Commitment
→ Fulfillment
→ explicit review
→ Financial Obligation
~~~

### S-EA — Obligation/Settlement ↔ Accounting

~~~text
recognized economic event
→ explicit Accounting posting Action
→ JournalEntry
~~~

### S-T* — Any temporal node ↔ Calendar

Each node gets a projection provider, source link and policy.

### S-H* — Any node ↔ Ambient header

Optional compact projection/widget.

### S-C* — Any relevant view ↔ Capability Launcher

Expose explicit available actions without hidden coupling.

## Composition review — human-centered flows

After key nodes and seams are accepted, test compositions that can stop at any level.

### Personal activity

~~~text
Plan
~~~

Optional:

~~~text
Plan
+ reminder
+ note/evidence
+ expense tracking
~~~

### Paid work

Possible progression:

~~~text
Plan only
~~~

or:

~~~text
Relationship
→ Contract
→ Commitment
→ Planner
~~~

then optionally:

~~~text
→ Fulfillment
→ Financial Obligation
→ Settlement
→ Accounting
~~~

### Business transaction

A user may begin from Need/Offer, known Relationship, direct Proposal, direct Contract or simple finance record depending on what is genuinely known/required.

Do not force discovery or negotiation layers when unnecessary.

## Automation review

Automation is reviewed only after the relevant manual Action works correctly.

Every automation must have explicit definition, visible trigger, explicit activation, scoped authority, idempotency, audit/result history, pause/deactivate and safe retry behavior.

Potential future seams include recurring Planner materialization, reminders, scheduled financial obligations and prepared/scheduled transactions where permitted.

Automation must not silently accept Contracts, approve reviews or move external money.

## Browser acceptance model

For a node:

1. prove standalone use;
2. browser-test it independently;
3. accept the node;
4. later review each optional seam separately;
5. browser-test compositions without losing the simple standalone path.

A later seam defect does not invalidate the standalone node unless it exposes a real node defect.

## Source-selection rule

Never choose a source branch by age alone.

For each capability, compare accepted assembly, all later candidate branches touching the same concern, tests, migrations, browser evidence, authority/privacy behavior and coupling.

Select or refactor the strongest coherent version.

## Current execution gate

The current assembly is a valid technical foundation but not yet the intended product checkpoint.

M01 registration work is paused.

Next implementation branch after this roadmap revision:

~~~text
codex/review-f1-shared-temporal-fabric
~~~

F1 review order:

1. F1A Temporal Kernel;
2. F1B Ambient Capability Rail;
3. F1C Fractal Calendar Fabric;
4. F1D Capability Launcher contract.

The first three already have substantial candidate implementations and previous owner browser familiarity, so the task is recovery + second review + decoupling, not greenfield implementation.
