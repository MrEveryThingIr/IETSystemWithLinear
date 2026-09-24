# Phase 12 — Personal Activity / Planner

## Status

Remote implementation complete and green on `feat/ideal-v1-12-personal-activity-planner`.

Final runtime checkpoint:

~~~text
SHA: 8d9f5690ba73daf0f73e03d86981d076ade1cd4e
GitHub Actions: 36037941878
458 tests / 2615 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Kernel checkpoint:

~~~text
SHA: 84dfcf76aca7a3a2df1b9c4c955fde82eb14a06a
GitHub Actions: 36035381347
454 tests / 2581 assertions
~~~

Baseline: Phase 11 integration merge `912d057c917532c183a7d45f405efe25e4674555`.

## Purpose

Provide one timezone-aware Planner for everyday personal and collaborative activity without turning schedules into Contract, employment, ownership, payment, or obligation authority.

## Implemented kernel

Phase 12 adds:

- `Plan`;
- `PlanParticipant`;
- immutable `PlanScheduleRule`;
- materialized `PlanOccurrence`;
- `PlanReminder`;
- immutable Plan and Occurrence event streams;
- same-Context Asset and exact Content Evidence Reference attachment;
- rolling horizon materialization command/schedule;
- Plan/Occurrence lifecycle Actions and policies.

Planner is enabled for Personal, GroupSpace and active Relationship Contexts.

## Schedule semantics

Supported schedule frequencies:

- one time;
- daily;
- weekly;
- selected dates.

Rules preserve local clock time in the Plan timezone across DST. They support interval, weekday selection, end date, occurrence limit, early/late execution windows and reminder offsets.

Schedule Rules are immutable scheduling provenance: replace/cancel rather than rewrite historical scheduling truth.

Occurrences store scheduled local/UTC-derived times plus actual start/end/completion state.

## User experience

The product now exposes:

- **Planner** in normal advanced navigation;
- **Today**;
- **List**;
- **Calendar**;
- **New plan**;
- Plan detail with participants, schedule rules, reminders, occurrences and lifecycle;
- Start / Complete / Skip / Cancel occurrence Actions;
- Pause / Resume / Complete / Cancel Plan Actions;
- same-Context evidence attachment;
- Planner entry from active Relationship and GroupSpace surfaces;
- Planner events inside the existing unified Context Timeline.

The default creation flow uses the authenticated Actor's Personal Context.

When launched from an active Relationship Context, the Plan records Relationship provenance and active Relationship participants/roles without creating any new Membership or Contract authority.

## Authority boundary

Planner records planning/execution facts only.

A Plan or Occurrence does **not** by itself prove:

- accepted Proposal or Contract terms;
- employment;
- ownership/equity;
- amount owed;
- financial obligation;
- payment/settlement;
- Fulfillment acceptance.

Relationship status/events do not change merely because a Relationship-sourced Plan is created or executed.

Later Contract/Commitment/Fulfillment phases may explicitly connect authoritative obligations to Planner occurrences.

## Time and reminder semantics

User/Plan timezone is explicit.

The kernel proves DST-safe recurrence: local clock time remains stable while UTC offset changes.

Reminder rows are the durable Phase-12 reminder seam. Actual notification delivery is intentionally deferred to the later Realtime + Notifications phase.

A scheduled daily command extends active rolling recurrence horizons:

~~~text
planner:materialize --days=120
~~~

## Evidence

An occurrence may attach existing Assets and exact Content Evidence References only from its Plan Context.

No file/Content duplication occurs and cross-Context evidence is rejected.

## Timeline composition

Phase 12 extends Phase 11's computed Timeline rather than creating a Planner-specific history surface.

Timeline now projects:

- Plan events;
- Occurrence events;

and links them back to the Plan/Occurrence source.

Plan/Occurrence event tables remain authoritative; Timeline remains read-only projection code.

## Story proof

### Bob — personal activity

Bob creates a one-time appointment or recurring study activity in his Personal Context.

He sees it in Today/List/Calendar, receives stored reminder offsets, starts/completes an occurrence, and the system records actual execution times.

### Alice ↔ Bob — Riverside selected workdays

Inside their active Relationship, Alice creates **Riverside selected workdays** for selected dates such as:

- 2026-09-25;
- 2026-09-27;
- 2026-10-02;

with 08:00 start and 540-minute duration.

The Plan preserves Relationship provenance and Bob's participant role. It does not alter Relationship lifecycle, create Group Membership, or create Contract/payment authority.

## Remote validation

Final CI `36037941878` proves:

- PHPUnit: **458 passed / 2615 assertions**;
- PHPStan: no errors;
- formatting gate: passed;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler/database-queue smoke: passed;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused tests prove:

- timezone/DST-safe schedule materialization;
- one-time/daily/weekly/selected-date recurrence;
- idempotent occurrence materialization;
- Plan and Occurrence lifecycle history;
- actual start/end/completion;
- same-Context evidence enforcement;
- Relationship provenance without authority escalation;
- Today/List/Calendar rendering;
- relationship/private authorization;
- Planner events in unified Timeline.

## Deferred

- actual reminder/notification delivery;
- drag/drop calendar scheduling and richer calendar ergonomics;
- participant reassignment/delegation workflows;
- Contract-linked Commitments;
- Fulfillment review/acceptance;
- financial consequences;
- local/browser/mobile/RTL/accessibility cumulative acceptance.

## Exit gate

Phase 12 exits because one Planner now supports personal and Relationship-sourced activity, timezone-safe recurrence, actual execution, evidence, reminders seam, Today/List/Calendar views and Timeline composition without becoming Contract authority.

Next: **Phase 13 — Personal Accounting v1**.
