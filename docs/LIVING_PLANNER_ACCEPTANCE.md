# Living Planner milestone acceptance

Successor branch `feat/living-planner`; production and the release branch are not modified by these milestones. A real production-data rehearsal remains unavailable.

## A — architecture and audit

Documented in `LIVING_PLANNER_ARCHITECTURE.md` before implementation. Existing Plan/Occurrence/ScheduleRule/Event/Participant/Context and authorization boundaries are retained. Personal commitment factors and future consent-bound sharing are defined; no automatic employability/personality score or sharing of reflections.

## B — structure and recurrence foundation

Adds private purposes, idempotent authorized purpose links, context-locked cycle-safe hierarchy, and personal reusable routine compositions within existing PlanTemplate. Reuse copies authorized definitions only, not occurrences, participants, private lab reflections or execution history. Distant recurrence counts reuse the existing matcher without materialization; a year of daily repetition is summarized without writing hundreds of extra rows. Existing schedule/timezone/calendar behavior is preserved.

Focused tests cover hierarchy cycles and duplicate changes, purpose privacy/deduplication, reuse without history, and a 365-occurrence virtual horizon with no extra materialization. UI/browser scenarios will be exercised with the progressively disclosed lab views in F. B is a domain foundation, not a claim that the complete Living Planner is finished.

## C — living execution foundation

Adds versioned policy snapshots, participant execution, pause/resume segments, late reporting, immutable correction chains and idempotent reconciliation. The scheduled command closes elapsed windows as unconfirmed, with separate effective/processing timestamps and system attribution. Explicit living policies and participant records route old start/complete controls through the same service; financial mark-done shortcuts cannot bypass these policies.

Focused plus kernel verification: 16 tests, 69 assertions. Covers active duration, retries, scheduler catch-up, flexible versus hard windows, immutable corrections with stale-edit rejection, group completion isolation and retained policy versions. All migrations were applied to disposable in-memory SQLite by the tests; cross-database migration and browser acceptance remain part of F. No production-data compatibility claim is made.

## D — commitment factors

Actor-specific measurements preserve target and actual start/end, signed deviation, active duration, acknowledged attendance, outcome, confidence and source. Shared occurrence completion is not attributed to another actor without that actor's report. Unique occurrence rows prevent duplicate hierarchy contributions; pending, upcoming, excluded and unconfirmed rows remain distinct. Completion percentages state the reported denominator alongside the full expected count.

An explicit recipient/purpose/expiry consent service returns a fixed sanitized snapshot, rechecks source-plan permission on read and supports revocation. Reflections and experiments are never included; no obligation, fulfillment, agreement or hiring decision is triggered.

Focused factor tests verify unknown denominators, delayed completion, hierarchy deduplication and recipient/purpose/revocation boundaries. Domain verification through D: 12 tests, 51 assertions. Browser and cross-database migration checks remain pending F.
