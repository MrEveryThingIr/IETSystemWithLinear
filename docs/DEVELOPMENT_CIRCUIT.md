# IET Development Circuit

## Purpose

IET currently operates in **browser-gated selective assembly mode**.

The repository is the durable source of truth. Existing modules are reused and revised; the project is not rewritten from scratch.

The active execution roadmap is `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`.

## Circuit

~~~text
owner-approved architecture
→ accepted assembly checkpoint
→ one module review branch
→ current-behavior audit
→ candidate/source audit
→ living module pre-plan
→ selective implementation
→ remote quality gate
→ owner local/browser gate on exact review head
→ correction + regression loop if needed
→ owner acceptance
→ PR merge into selective assembly
→ post-merge CI
→ frozen checkpoint + next wiring plan
→ next module
→ ...
→ cumulative release candidate
→ final end-to-end acceptance
→ stable release
~~~

## Authority order

1. human owner;
2. accepted ADRs / explicit architecture decisions;
3. `docs/PROJECT_COMPASS.md`;
4. `docs/CURRENT_STATE.md`;
5. `docs/TARGET_ARCHITECTURE.md`;
6. `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`;
7. `docs/PRODUCTION_ROADMAP.md`;
8. `docs/CONTINUOUS_REMOTE_EXECUTION.md`;
9. active module contract/report;
10. matching `.ai/rules/`;
11. source code/tests for implemented behavior;
12. historical reports/handoffs;
13. issue trackers and chat history.

## Agent startup

Before code:

1. read `AGENTS.md`;
2. read matching `.ai/rules/`;
3. read canonical architecture docs;
4. read `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`;
5. read `docs/EXAMPLE_STORY_WORLD.md`;
6. inspect current `codex/ideal-v1-selective-assembly` SHA and CI;
7. inspect active review branch/PR/report if present;
8. inspect source branches/commits identified by the report;
9. continue from repository evidence, never presumed chat memory.

## Module cycle

### 1. Branch

Create one coherent review branch from the current accepted assembly SHA.

### 2. Inspect

Audit exact current behavior: migrations, models, Actions, policies, UI, tests, docs and applicable rules.

Then inspect candidate/source branches. Treat them as implementation evidence, not merge units.

### 3. Preserve the pre-plan

Create/update `Development-CodexReports/Mxx-<module>-report.md` before implementation.

Record objective, current behavior, candidates, authority/dependencies/consumers, wiring contract, risks, proposed improvements, rollback, validation and browser plan.

Keep this plan visible when later appending actual decisions. Do not rewrite history into a hindsight-only report.

### 4. Implement selectively

Prefer proven kernels. Import only selected files/hunks/ideas. Improve the module where browser/architecture/review evidence justifies it.

Use atomic commits such as:

~~~text
feat(...)
fix(...)
test(...)
docs(...)
~~~

### 5. Validate remotely

Where applicable:

~~~text
focused PHPUnit
full PHPUnit
PHPStan
Pint
Blade compile
Vite production build
migration rollback/reapply
scheduler/queue smoke
backup/restore smoke
npm audit
Composer audit
~~~

Also test authorization/privacy/concurrency/idempotency where meaningful.

### 6. Browser acceptance before merge

The owner switches locally to the review branch and follows its worksheet.

Test the module independently and against previously accepted modules only. Future modules must not be required merely to make the current module appear functional.

If a defect is found:

~~~text
browser finding
→ document
→ automated reproduction where practical
→ correction commit
→ focused/full remote validation
→ repeat affected browser checks
~~~

Only explicit owner acceptance closes this gate.

### 7. Merge accepted head

Merge the exact browser-accepted review head into `codex/ideal-v1-selective-assembly` through PR, then require green post-merge CI.

### 8. Freeze checkpoint and plan next wiring

Update:

- module report;
- `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`;
- `docs/handoffs/continuous-ideal-v1.md`;
- `docs/CURRENT_STATE.md`;
- System Manual when user-facing behavior changed.

Record final assembly SHA/CI and exactly how the next module may consume this module.

## Git topology

~~~text
main                                   public/stable
  ↑
release/*                              final stabilization
  ↑
codex/ideal-v1-selective-assembly     browser-accepted cumulative assembly
  ↑
codex/review-*                         one module under review
  ← candidate/source branches          read/cherry-pick selectively
~~~

Never force-reset historical branches just to simplify the graph.

## Database discipline

No `migrate:fresh` against the continuing acceptance database.

Shared migrations stay append-only. Browser-discovered fixes are correction commits/migrations, never silent history rewrites.

## Documentation story continuity

Use `docs/EXAMPLE_STORY_WORLD.md`:

- Diego — platform/office operator;
- Alice — owner/client;
- Bob — service provider/worker;
- Carol — collaborator/capital/reviewer where appropriate;
- Maple Housing Office;
- Riverside Home Project.

Reuse these objects as modules become wired together.

## Stop conditions

Stop only for genuine architecture contradiction, unavoidable destructive/data loss, unresolved authorization/legal/financial meaning, owner approval for dependency/credential/provider, external-money/custody boundary, unsafe Git state, or unavailable required validation.

Ordinary defects are handled inside the current module correction loop.

## Handoff

`docs/handoffs/continuous-ideal-v1.md` is the short restart point. `docs/SELECTIVE_ASSEMBLY_ROADMAP.md` is the active execution roadmap.
