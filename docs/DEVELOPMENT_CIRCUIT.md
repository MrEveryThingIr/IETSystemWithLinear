# IET Development Circuit

## Purpose

IET now operates in **continuous remote roadmap mode**.

The repository remains the durable source of truth. The owner has explicitly deferred local Laragon/browser acceptance until the remotely integrated Ideal-v1 roadmap is complete enough for the cumulative 0→100 worksheet.

The circuit is:

~~~text
owner-approved architecture
→ canonical repository docs
→ one remote milestone branch
→ implementation + migrations + tests + manual source
→ remote CI gate
→ milestone report + acceptance worksheet entry
→ integration/ideal-v1 checkpoint
→ next milestone
→ ...
→ integrated release candidate
→ owner local/browser 0→100 validation
→ correction pass
→ release
~~~

## Authority order

1. human owner;
2. accepted ADRs / explicit architecture decisions;
3. `docs/PROJECT_COMPASS.md`;
4. `docs/CURRENT_STATE.md`;
5. `docs/TARGET_ARCHITECTURE.md`;
6. `docs/PRODUCTION_ROADMAP.md`;
7. `docs/CONTINUOUS_REMOTE_EXECUTION.md`;
8. active milestone contract/report;
9. matching `.ai/rules/`;
10. source code and tests for implemented behavior;
11. historical reports/handoffs;
12. issue trackers and chat history.

## Agent startup

Before code:

1. read `AGENTS.md`;
2. read matching `.ai/rules/`;
3. read the canonical docs above;
4. read `docs/EXAMPLE_STORY_WORLD.md`;
5. inspect latest `integration/ideal-v1` SHA and CI;
6. inspect the active milestone branch/report if it exists;
7. continue from repository evidence, never from presumed chat memory.

## Milestone cycle

### 1. Branch

Create a coherent feature branch from the current integration SHA.

### 2. Inspect

Audit the exact current implementation, migrations, policies, Actions, UI, tests, docs and applicable rules before editing.

### 3. Implement

Prefer existing kernels. Add a new domain layer only when an existing object cannot truthfully own the state.

Use atomic commits such as:

~~~text
feat(...)
test(...)
docs(...)
fix(...)
~~~

### 4. Validate remotely

At minimum where applicable:

~~~text
focused PHPUnit
full PHPUnit
PHPStan
Pint
Vite production build
migration rollback/reapply
scheduler/queue smoke
backup/restore smoke
npm audit
Composer audit
~~~

Also add authorization/privacy/concurrency/idempotency tests when the milestone can fail in those dimensions.

### 5. Document as versioned product knowledge

Update both Markdown authority and `SystemManualContent`.

Manual pages must document exact controls/actions and reuse the Alice/Bob/Carol/Diego story.

### 6. Record checkpoint

Write/update:

- `Development-CodexReports/<milestone>-report.md`;
- `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`;
- `docs/handoffs/continuous-ideal-v1.md`;
- `docs/CURRENT_STATE.md`.

Record exact result SHA and CI run.

### 7. Integrate and continue

When remote gates are green and no stop condition exists, integrate into `integration/ideal-v1` and activate the next milestone without waiting for local browser acceptance.

## Git topology

~~~text
main                         public/stable release line
  ↑
release/*                    release stabilization after final human gate
  ↑
integration/ideal-v1         cumulative remotely-green Ideal-v1
  ↑
feat/ideal-v1-*              one remote milestone
~~~

Legacy branches remain historical evidence. Never force-reset them just to make the graph prettier.

## Deferred local gate

The owner later follows `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` in chronological order using the continuing database.

No `migrate:fresh`.

Failures discovered during deferred acceptance become new correction commits on the current integration/release line with regression tests. Historical checkpoint SHAs remain honest.

## Documentation story continuity

The canonical cast is defined in `docs/EXAMPLE_STORY_WORLD.md`.

Prefer:

- Diego — office operator / Group owner;
- Alice — property owner/requester;
- Bob — service provider/worker;
- Carol — capital provider;
- Maple Housing Office;
- Riverside Home Project.

Do not invent unrelated example users on every page unless a genuinely different role is needed.

## Stop conditions

Stop only for:

- contradictory canonical architecture;
- unavoidable destructive/data-losing migration;
- unresolved authorization or legal/financial semantics;
- owner approval required for dependency/credential/provider;
- external-money/custody regulatory boundary;
- unsafe Git operation;
- required validation unavailable.

Normal implementation defects should be repaired and the milestone continued.

## Handoff

`docs/handoffs/continuous-ideal-v1.md` is the short operational restart point after interruption. Canonical architecture remains in the main docs.
