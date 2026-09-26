# Selective Assembly Roadmap

## Purpose

This is the active execution roadmap for rebuilding the first publishable Ideal-v1 from the already-developed IET modules.

The work is **not a rewrite from scratch**. Existing integrated code and later candidate branches are source material. Each domain is independently inspected, revised where justified, proven remotely, inspected by the owner in the browser, corrected if needed, and only then frozen into the selective assembly.

The assembly branch is:

~~~text
codex/ideal-v1-selective-assembly
~~~

The initial accepted foundation was `891b333c49f166e61b9fa466e30742b3d70996c0`. S0 strengthened and certified that foundation. The current S0 closure checkpoint is documented in `docs/handoffs/S0-selective-assembly-baseline.md`.

## Authority

This roadmap controls **execution order and admission gates** for the selective reassembly.

It does not replace:

- `docs/PROJECT_COMPASS.md` for product purpose and invariants;
- `docs/TARGET_ARCHITECTURE.md` for intended technical boundaries;
- `docs/PRODUCTION_ROADMAP.md` for the broader historical/product roadmap;
- ADRs and domain reports for settled domain decisions.

When an older process document says browser acceptance may be deferred until the end, this roadmap supersedes that process for the selective assembly.

## Core rule

A module is not accepted merely because its tests are green.

The sequence is:

~~~text
accepted assembly checkpoint
→ dedicated review branch
→ inspect current behavior
→ inspect candidate/source branches
→ write/update module pre-plan
→ implement only selected improvements
→ focused tests
→ full remote CI
→ owner local/browser acceptance on the review branch
→ regression-test and fix every accepted defect
→ repeat CI + browser checks until accepted
→ merge the exact accepted review head into assembly
→ post-merge CI
→ freeze checkpoint evidence
→ plan the next module and its wiring
~~~

**Do not begin implementation of the next module before the current module's browser gate is accepted.**

S0 is the one historical exception because the browser-gated policy was adopted after S0 was remotely merged. Therefore the next action before M1 implementation is an S0 baseline browser smoke on the assembly branch.

## Why branch reassembly instead of a new repository

The current repository preserves migration history, tests, reports, ADRs, provenance, and candidate branches. The selective assembly branch gives the same clean admission discipline without discarding that evidence.

A new repository is not required unless a later release decision explicitly chooses history separation.

## Module admission record

Before implementation, every module report must contain:

1. **Module objective** — what human problem this layer solves.
2. **Current behavior snapshot** — what the accepted assembly does now.
3. **Candidate inventory** — source branches/commits/files and why each is relevant.
4. **Authority map** — truth owned here and truth explicitly owned elsewhere.
5. **Dependency map** — accepted modules this layer consumes.
6. **Consumer map** — later modules expected to consume it.
7. **Wiring contract** — allowed data/action/event seams between modules.
8. **Risk review** — authorization, privacy, data loss, concurrency, temporal, money, locale/RTL, accessibility, migration and operations.
9. **Proposed improvements** — ranked as required / recommended / deferred.
10. **Rollback plan** — how the candidate can be removed without rewriting accepted history.
11. **Remote validation plan**.
12. **Browser acceptance script**.
13. **Open questions and explicit deferrals**.

During implementation, update the same report with decisions actually taken. Do not replace the original pre-plan with a hindsight-only summary.

After acceptance, append exact commit, PR, CI, browser evidence, defects found, correction commits, final assembly SHA, and next-module wiring notes.

## Browser gate

The owner inspects the **review branch before merge**.

For each module:

- sync the exact review head;
- migrate forward only on the continuing local database;
- run the focused local test commands recorded for the module;
- inspect desktop and representative mobile behavior;
- inspect English plus relevant Persian/Arabic/Chinese/RTL surfaces when the module presents localized UI;
- exercise normal, empty, invalid, unauthorized, terminal and recovery states that matter to the module;
- verify durable results after reload;
- verify actions do not silently create authority belonging to another domain;
- record defects in the module report/worksheet;
- reproduce release-blocking defects with automated regression coverage before correction.

A module is **browser accepted** only when the owner explicitly reports acceptance of the tested head. Silence is not acceptance.

## Assembly branch discipline

The assembly branch contains only accepted checkpoints.

- Never develop directly on `codex/ideal-v1-selective-assembly`.
- Open one review branch from the current assembly head.
- Candidate branches are source libraries, never wholesale merge units.
- Keep the PR draft/open while the module is under remote or browser review.
- Merge only the exact browser-accepted head.
- Require post-merge assembly CI.
- Never force-push accepted history.
- Shared migrations are append-only.
- Never use `migrate:fresh` on the owner's continuing acceptance database.
- Keep rejected source branches until stable release.

## Dependency-aware assembly order

The labels below are the current module-review order. Existing functionality may already be present in the accepted foundation; the purpose is to **re-review and selectively improve it**, not to imply it is absent.

### M0 — Certified foundation and browser baseline

Status: remote S0 certification complete.

Scope:

- CI truth;
- migrations/operations baseline;
- Blade compilation;
- representative navigation/localization rendering;
- verify formatter-only S0 repair changed no product behavior.

Exit: owner browser smoke of the certified assembly is accepted.

Historical mapping: S0.

### M1 — Access, identity, registration provisioning, personal wallet defaults

Review together because registration establishes the user's first durable personal capabilities.

Scope:

- invitations/registration/verification handoff;
- User ↔ Actor boundary;
- verified-user provisioning action;
- personal Context/accounting foundation where appropriate;
- default MonetaryUnit preference;
- changeability/idempotency;
- safe repeatable seed/bootstrap behavior.

Do not make registration silently create contracts, group membership, financial obligations, or business relationships.

Historical mapping: S1.

### M2 — Shared temporal/localization presentation kernel

This must stabilize before time-bearing business modules are re-reviewed.

Scope:

- timezone preference;
- locale/calendar preference;
- canonical UTC persistence vs local presentation;
- Jalali/Gregorian/Hijri presentation rules as supported;
- equalized secondary date/time presentation where required;
- parsing/formatting helpers;
- permanent status/shell presentation if retained;
- DST and boundary behavior;
- cross-locale RTL implications.

Historical mapping: S2 + S3 + temporal part of S6.

### M3 — Context, Content, Assets and immutable Evidence

Review the shared human-facing artifact/evidence substrate before Planner, Contracts and Submissions consume it.

Scope:

- Context authorization;
- Content identity/revisions/publication;
- Blocks/fields/presentation;
- private Assets;
- exact Evidence References;
- cross-Context placement/reference without authority leakage;
- Reader/Studio separation;
- provenance and historical pinning.

Exit: later modules can reference exact evidence without copying or mutating it.

### M4 — Groups, membership, admissions and Group Agreements

Review governance before business compositions depend on organizations/groups.

Scope:

- Group lifecycle;
- Membership/roles/permissions;
- invitation → Admission → Membership;
- required Agreement versions and immutable acceptance;
- ownership transfer/integrity;
- GroupSpace access;
- Agreement vs negotiated Contract boundary.

No Group role may imply platform-wide authority.

### M5 — Planner and fractal calendar

Planner is inspected only after temporal and evidence foundations are accepted.

Scope:

- Plan/ScheduleRule/Occurrence/Participant;
- early-start/waiting/running/completion lifecycle;
- actual start/end;
- evidence attachment/reference;
- recurrence materialization;
- reminders;
- list/Today/calendar;
- drill-down calendar from broad periods to minute slots;
- creation prefill from selected slots;
- authorized projections from other domains.

Historical mapping: S4 + S5.

### M6 — Personal Accounting and monetary reporting

Review personal money tracking independently from obligations/contracts.

Scope:

- MonetaryUnit;
- Ledger/Account;
- balanced immutable JournalEntry/JournalLine;
- opening balance, expense, income, transfer;
- reversal/correction;
- per-unit summaries;
- default monetary unit UX;
- no cross-currency magic total.

Personal Accounting is not automatically authoritative for Contract obligations or Settlement claims.

### M7 — Profile Intent, Need/Offer and matching

Review human intent/discovery before relationship/business obligation layers.

Scope:

- Profile intent semantics;
- Need vs Offer;
- Concept/subject/arrangement/quantity/location/time/value constraints;
- visibility/privacy;
- deterministic explainable matching;
- selected-candidate revalidation;
- exact provenance when handing off to a Relationship.

Matching creates no obligation.

### M8 — Relationship, Conversation and Timeline

Review coordination independently from contractual authority.

Scope:

- Relationship participants/roles/lifecycle;
- Relationship Context authorization;
- Conversation/messages/replies/attachments;
- Timeline reconstruction from source events;
- read-only behavior after terminal states;
- direct request vs Need/Offer origin.

Conversation wording never equals formal acceptance.

### M9 — Proposal, negotiation, Contract and ContractVersion

Review negotiated business authority after relationships are accepted.

Scope:

- Proposal lifecycle;
- negotiation Context;
- immutable proposed terms;
- Contract identity;
- immutable ContractVersion;
- exact parties/roles;
- required-party acceptance;
- activation/effective/supersession/amendment;
- Group Agreement vs party-specific Contract distinction.

No Contract becomes active from chat text alone.

### M10 — Commitment and Fulfillment

Review obligation-to-act and evidence-of-what-happened as distinct truth.

Scope:

- Commitments created from explicit authority;
- Planner binding/materialization;
- Fulfillment actuals;
- duration/quantity/status;
- exact Content/Asset evidence;
- review/clarification/rejection/acceptance;
- dispute/correction semantics.

Planner completion alone must not manufacture financial truth.

### M11 — Financial Obligation, Settlement and accounting bridge

Only after Contract/Commitment/Fulfillment and Personal Accounting are independently accepted.

Scope:

- economic-event → FinancialObligation rules;
- debtor/creditor/privacy;
- pending/confirmed/rejected Settlement;
- paid/outstanding/disputed derivation;
- explicit posting into Personal Accounting;
- idempotency and reversal/correction seams.

No mutable magic balance.

### M12 — Structured Interaction, Submission and Evaluation

Review generic structured response/review separately from chat annotations.

Scope:

- InteractionDefinition;
- Submission/Response;
- evidence;
- reviewer authorization;
- Evaluation;
- status transitions;
- applicant/reviewer UX;
- no hidden equivalence between annotation and evaluation.

### M13 — Notifications, realtime and Home/Today

Review derived attention surfaces only after source domains are stable.

Scope:

- transactional outbox;
- durable notification inbox/read state;
- authorized realtime transport;
- idempotent reminders;
- Today/waiting-on-me/waiting-on-others;
- source links;
- no duplicate domain truth;
- reconstruction when realtime transport is absent.

### M14 — Domain/Business Blueprints and composed journeys

Now re-review the higher-level human flows built from accepted kernels.

Initial proof journeys:

- personal activity;
- simple sale;
- rental;
- service job;
- employment/paid work;
- construction partnership;
- Group/community collaboration.

Blueprints guide composition and terminology. They never bypass domain Actions or grant authority.

This is the phase for revised **wiring ideas between nodes**: which accepted modules appear together, what starts the next capability, and what remains optional.

### M15 — Cross-system consistency, flexible extensions, polish and release

Scope:

- cross-system temporal consistency;
- localization/RTL/accessibility;
- responsive UX;
- consistent navigation/action menus/state feedback;
- flexible fields/form extensions only where proven necessary;
- operations/security/privacy/performance;
- cumulative end-to-end browser story;
- release candidate freeze and stable publication.

Historical mapping: remaining S6 + S7 + S8 + S9.

## Wiring review rule

Before connecting two accepted modules, document the seam explicitly:

~~~text
source authority
→ explicit Action/event/reference
→ target capability
→ durable result
→ authorization check
→ idempotency/concurrency rule
→ what is NOT implied
~~~

Examples:

~~~text
accepted ContractVersion
→ explicit Commitment creation Action
→ Commitment
→ optional Planner binding
→ PlanOccurrence
→ actual Fulfillment
→ accepted economic event
→ FinancialObligation
→ explicit Accounting posting Action
→ JournalEntry
~~~

and:

~~~text
Need/Offer match
→ explicit Relationship proposal
→ counterparty acceptance
→ Relationship Context
→ optional Proposal
→ explicit Contract creation/acceptance
~~~

Do not connect modules by database side effects hidden in views, observers, or casual Content/Conversation wording when an explicit domain Action is required.

## Milestone files

For every M-module, maintain:

- `Development-CodexReports/Mxx-<module>-report.md` — living pre-plan + implementation + review record;
- `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` — exact local commands and browser checklist;
- `docs/handoffs/continuous-ideal-v1.md` — short restart state;
- `docs/CURRENT_STATE.md` — accepted checkpoint and current next step.

Update `App\Support\SystemManualContent` when user-facing behavior changes.

## Current gate

M0/S0 remote certification is complete. Because the browser-gated assembly policy was adopted afterward, **M1 implementation must not begin until the owner completes and accepts the S0 baseline browser smoke on the current assembly branch**.

If S0 browser inspection finds a defect:

1. create a dedicated correction branch from the assembly head;
2. reproduce the defect with automated coverage where practical;
3. fix only that baseline defect;
4. rerun remote CI;
5. repeat the affected browser checks;
6. merge the correction through PR;
7. record the corrected assembly checkpoint;
8. then begin M1.
