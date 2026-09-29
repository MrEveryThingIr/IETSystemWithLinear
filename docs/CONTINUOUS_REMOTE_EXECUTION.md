# Selective Assembly Execution Mode

## Current authorization

The owner has replaced the old end-deferred browser model with **browser-gated selective assembly**.

The active execution authority is `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`.

Historical remote milestones and their CI remain valuable evidence, but a module is not admitted into the current assembly solely because a historical or new remote CI run is green.

## Assembly line

~~~text
codex/ideal-v1-selective-assembly
~~~

Candidate and historical branches are source libraries. Never merge an aggregate candidate branch wholesale merely because it contains several desired improvements.

## Module cycle

~~~text
accepted assembly
→ codex/review-<module>
→ inspect accepted behavior
→ inspect source/candidate implementations
→ living pre-plan/report
→ selective implementation
→ focused tests
→ full remote CI
→ owner local/browser acceptance on review head
→ defect → regression → correction loop as needed
→ explicit owner acceptance
→ merge exact accepted head into assembly
→ post-merge CI
→ freeze checkpoint evidence
→ next module
~~~

No next-module implementation begins before the current module's browser gate is accepted.

## Required remote gate

At minimum, where applicable:

- focused PHPUnit;
- full PHPUnit;
- PHPStan;
- Pint;
- Blade compilation;
- Vite production build;
- migration forward + rollback/reapply smoke;
- scheduler/database-queue smoke;
- backup/restore smoke;
- npm audit;
- Composer audit;
- authorization/privacy regression tests;
- concurrency/idempotency tests for race-sensitive transitions.

Never report a gate as passed unless an actually executed command proves it.

## Required browser gate

The owner tests the exact review head before merge.

At minimum, where relevant:

- normal journey;
- empty state;
- invalid input;
- unauthorized/cross-context attempt;
- terminal/recovery state;
- reload/persistence;
- desktop and representative mobile behavior;
- English and affected Persian/Arabic/Chinese/RTL surfaces;
- keyboard/focus/accessibility smoke;
- confirmation that the module did not acquire authority belonging to another domain.

Every release-blocking browser defect should receive automated regression coverage before correction when practical.

Silence is not acceptance. The report must record the accepted head explicitly.

## Checkpoint evidence

Every module maintains:

- `Development-CodexReports/Mxx-<module>-report.md` as a living pre-plan + implementation record;
- `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` with exact local commands/browser checks;
- `docs/handoffs/continuous-ideal-v1.md` as restart state;
- `docs/CURRENT_STATE.md` with the accepted checkpoint/current gate;
- System Manual updates when user-facing behavior changes.

Record source SHAs, selected changes, rejected alternatives, migration list, remote CI, browser evidence, correction commits, final assembly SHA and next wiring notes.

Do not erase the original pre-plan after implementation; preserve the evolution of the decision.

## Integration rules

- never develop directly on `main` or the assembly branch;
- no force-push of shared accepted history;
- no destructive rewrite of earlier checkpoints;
- shared migrations are append-only;
- never assume `migrate:fresh` on a continuing database;
- fixes found during browser review stay on the module/correction branch until accepted;
- later modules may consume earlier accepted domains but may not silently redefine their authority.

## Documentation-as-Content rule

Every material user-facing module updates both repository documentation and `App\Support\SystemManualContent`.

The manual must teach:

~~~text
WHO
→ WHERE in UI
→ WHAT is visible
→ WHAT to click/select/type
→ WHAT durable result is created
→ WHO can see it
→ WHAT it does not imply
~~~

Use `docs/EXAMPLE_STORY_WORLD.md` for Alice/Bob/Carol/Diego continuity.

## Stop conditions

Pause for:

- contradictory canonical architecture;
- destructive/data-losing migration;
- unresolved authorization/legal/financial semantics;
- dependency/credential/provider requiring owner approval;
- unsafe Git state;
- unavailable required validation;
- external-money/custody boundary.

A browser defect or ordinary test failure is not a reason to abandon the module. Reproduce, fix, retest, and continue its correction loop.

## Current gate

S0 remote baseline certification is complete.

Before M1 implementation, the owner must complete the S0 browser baseline smoke on the current assembly line. If defects are found, fix them on a dedicated correction branch and repeat the affected checks before M1 begins.
