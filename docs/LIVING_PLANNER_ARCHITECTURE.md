# Living Planner: repository audit and implementation contract

Milestone A. Successor branch `feat/living-planner`, based on the clock/release-hardening work in draft PR #79. No production changes. This document maps the existing repository before introducing Planner migrations or behavior.

## Existing and target concepts

| Target | Existing authority | Extension/gap |
|---|---|---|
| Independent personal planning | `Plan`, personal `Context`, `CreatePlan`, `PlanPolicy` | Preserve ordinary plans; no Work/Contract dependency |
| Goals and purpose | Plan metadata only | Private purpose records and optional contribution links |
| Hierarchy and routines | `PlanTemplate`, `PlanSeries`; no parent graph | Cycle-safe plan composition; distinct occurrence identities |
| Recurrence | Immutable `PlanScheduleRule`, cancel/replace; unique occurrence per rule/start | Future-only revisions with old snapshots; bounded display/materialization |
| Calendar horizons | `PlannerHorizons`, `TemporalCalendar`, fractal Planner calendar | Quarter/year/custom ranges and aggregate cells, no alternative preference system |
| Scheduled/actual time | Immutable occurrence schedule/window/timezone plus actual start/end | Preserve ideal; store policy snapshots and timing deviations separately |
| Execution lifecycle | `TransitionPlanOccurrence`, five coarse statuses; start/complete/skip/cancel | Pause/resume, segments, partial/interrupted outcomes, explicit reporting/correction |
| Temporal lifecycle | `windowState()` computes a `missed` label from elapsed time | Scheduler reconciliation; expired + unconfirmed is not proven nonperformance |
| Participants | Immutable plan-level `PlanParticipant` | Occurrence-level acknowledgments, attendance and individual execution |
| Historical evidence | Immutable `PlanOccurrenceEvent`, `AttachPlanOccurrenceEvidence` | Effective vs processing time, idempotency, correction links, provenance/confidence |
| Scheduler | Bounded `MaterializePlannerHorizon`, in-process shared-host scheduling, notification outbox | Idempotent catch-up and locked automatic window closure; no browser dependency |
| Readiness/resources | Prerequisites, checks, expenses, evidence | Preserve and reuse; experimentation stays optional |
| Integration | Immutable `CommitmentPlanBinding`, `CreateCommitmentPlan` and context policies | Explicit provenance and authorized outcome reads; never automatic fulfillment/finance/approval |
| Personal experiments | No dedicated hypothesis/observation/period comparison | Private optional experiments, cues, obstacles, reflections and observational summaries |
| Navigation | Planner routes exist; Work hub fronts the navigation | Plans becomes a distinct top-level destination with progressively disclosed lab views |

## Commitment factors requested by the owner

A commitment may be to oneself (a purpose, routine or personal growth activity) or to other participants. Measure the commitment **within its stated scope**, not the worth or personality of the person.

Retain: target count/duration, actual reported duration, start/end deviation, consistency over the chosen interval, acknowledged participation, explicit completed/partial/interrupted/missed reports, unconfirmed observations, evidence provenance and corrections. Show the denominator, observation period, cancellations/exclusions and evidence confidence. Insufficient or unconfirmed data must remain visible rather than becoming zero performance. A late completion remains completed with a retained timing deviation.

Private reflections, hypotheses, obstacle notes and personal-growth context are private by default, including when the underlying Plan is shared. They are never automatically disclosed to an employer, business, counterparty or contract reviewer.

Future employment, contract, agreement or deal use requires an explicit subject-authorized, recipient- and purpose-scoped sharing contract. The recipient must independently be authorized to see the linked Plan/evidence. Sharing an activity outcome does not share reflections. Self-reported information must be labeled as such; another participant's acknowledgment is not automatically independent verification. No universal trust/personality/employability score, automatic hiring judgment, causal diagnosis, financial obligation or agreement amendment is produced.

## State and authority rules

- Keep schedule definition, temporal phase, actual execution, participation, outcome and confidence distinct. Existing scheduled timestamps are immutable.
- Store new execution policies as versioned snapshots before use. A hard boundary cannot silently become flexible because an action was late.
- Expiration closes a window with effective time equal to its boundary and processing time equal to reconciliation time. With no report, outcome is `unconfirmed`; `missed` needs an explicit admissible report/evaluation.
- Existing completed/skipped/cancelled records remain interpretable. Extend the existing occurrence/event authority rather than copy occurrences into a second planner.
- Serialize transitions on the occurrence; key retries by occurrence/action/idempotency key. Repeated reconciliation or a race with a manual action cannot duplicate events or notifications.
- Pause/resume segments measure active duration. Late reports preserve asserted effective time, recorded time, author, evidence confidence and a correction chain. Do not erase prior facts.
- Parent aggregation counts each underlying occurrence once, regardless of how many purposes reference it. Parent links must be acyclic and authorized.
- Immutable schedule-rule replacements define revision scope: one occurrence exception, future occurrences, or a new definition. Past snapshots survive every revision.

## Atomic implementation milestones

A. This audit, factor definitions, migration/authority plan and acceptance contract.

B. Private purposes, optional contribution links, cycle-safe plan hierarchy/routines, bounded recurrence presentation and compact aggregate horizons. Preserve existing schedule creation and templates.

C. Deterministic execution/window reconciliation, participant acknowledgment/attendance, pause/resume/finish, flexible/hard/deadline policies, idempotency and effective/processing event times.

D. Target/actual measurements, outcomes/confidence, historical correction, timeframe/parent aggregation and explicit authorized integration reads.

E. Optional private experiments/observations, baseline/target comparisons, cues/obstacles/reflections and user-approved proposed adjustments; observed association is not claimed causation.

F. Plans navigation, Today/Calendar/Routines/Purposes/Insights/History, translations, desktop/mobile browser scenarios and complete regression/migration gates.

Each implementation commit must carry meaningful tests and a milestone acceptance update. Milestones are not marked accepted merely because their architecture is documented.

## Migration and verification plan

Use additive tables/nullable columns and preserve all existing IDs, schedule snapshots, keys, records and media. Index actor/owner, Plan, occurrence, temporal range and idempotency lookups. Private lab ownership follows Actor identity and active/verified User authorization. The deployment package remains v1.12 source until the successor is separately accepted.

Test untouched ordinary Planner behavior; unattended return after days; scheduler downtime/retries; early/punctual/late/partial execution; strict/flexible/deadline-only windows; participant privacy; duplicate and concurrent actions; corrections; cycle rejection; multiple purpose links without double counts; large recurrence horizons; DST and Jalali/Gregorian semantics. Run migrations on disposable SQLite and MySQL/MariaDB copies; never reset the development database. A real restored production-data upgrade/rollback remains unresolved until an actual backup is available.

Browser acceptance must include guest/authenticated clock preservation and the Planner's compact daily flow, routine creation, hierarchy, experiments and history in supported languages and viewport sizes. No personal questionnaire is required to create an ordinary Plan.
