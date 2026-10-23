# Living Planner milestone acceptance

## F — integration candidate (verification in progress)

Plans now has a dedicated `/plans` entry and Today, Calendar, Routines, Purposes,
Insights and History destinations. The Lab exposes personal activity creation,
execution outcomes, guided reporting/correction, purpose editing/archiving/linking,
routine reuse, period comparison, private observations/corrections and explicit
approved-adjustment application. Future and single-occurrence schedule revisions
retain original definitions and events. Today and History use visible pagination.

Elapsed unreported windows display unconfirmed in the legacy calendar too. Living
occurrence detail controls link to the same participant-aware execution surface.
Calendar recurrence summaries call the existing projection service. Reusable
routine shapes retain their execution policy. Fonts are built from the existing
local Inter/Vazirmatn packages without an unused external font request.

Current verification is a candidate, not final acceptance. Focused regression,
revision and UI tests have passed in separate runs; PHPStan passed. MySQL fresh
migrations and realistic seeding passed in `iet_living_planner_demo`. Two concurrent
reconciliation workers processed one overdue occurrence exactly once; inspection
found zero duplicate window events and zero remaining elapsed windows. Populated
MySQL upgrade/rollback, full regression and real-browser gates are still running.

The main checkout and its database are untouched. Only the Planner worktree's
ignored `.env` points to the explicitly named local demo database. Credentials and
browser artifacts are ignored under `storage/release-acceptance/living`.

Local demo setup (local MySQL only; preserves the source database and APP_KEY):

```powershell
php tools/prepare-living-demo.php C:/laragon/www/EveryThing/.env
php tools/living-planner-demo.php
npm.cmd ci
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8002
```

Run these from the Living Planner checkout. The demo script rejects ordinary
database names and never resets an existing database. It preserves existing demo
accounts rather than overwriting them. A partial fixture setup requires explicit
inspection before retrying. The development server uses the checkout's `.env`.

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

## E — optional private experiments

Adds immutable experiment definitions, private observations with append-only corrections, selected changes, cue-response plans, obstacles, difficulty and reflections. Equally long baseline/current periods compare observed means and sample sizes; missing observations remain missing. Declared baseline and target are never silently rewritten.

Adjustment proposals require an explicit owner decision, recorded once; approval does not change a Plan or its targets. Insights label self-reporting and association without causal or psychological conclusions. Ordinary plan creation does not depend on this module.

Domain suite through E: 14 tests, 61 assertions, plus PHPStan with zero errors. Tests cover private owner access, baseline comparison, idempotent correction, immutable original observations and approval without automatic target changes. Browser UX and migration portability remain pending F.
