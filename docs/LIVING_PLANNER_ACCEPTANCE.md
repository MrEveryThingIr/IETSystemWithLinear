# Living Planner milestone acceptance

Successor branch `feat/living-planner`; production and the release branch are not modified by these milestones. A real production-data rehearsal remains unavailable.

## A — architecture and audit

Documented in `LIVING_PLANNER_ARCHITECTURE.md` before implementation. Existing Plan/Occurrence/ScheduleRule/Event/Participant/Context and authorization boundaries are retained. Personal commitment factors and future consent-bound sharing are defined; no automatic employability/personality score or sharing of reflections.

## B — structure and recurrence foundation

Adds private purposes, idempotent authorized purpose links, context-locked cycle-safe hierarchy, and personal reusable routine compositions within existing PlanTemplate. Reuse copies authorized definitions only, not occurrences, participants, private lab reflections or execution history. Distant recurrence counts reuse the existing matcher without materialization; a year of daily repetition is summarized without writing hundreds of extra rows. Existing schedule/timezone/calendar behavior is preserved.

Focused tests cover hierarchy cycles and duplicate changes, purpose privacy/deduplication, reuse without history, and a 365-occurrence virtual horizon with no extra materialization. UI/browser scenarios will be exercised with the progressively disclosed lab views in F. B is a domain foundation, not a claim that the complete Living Planner is finished.
