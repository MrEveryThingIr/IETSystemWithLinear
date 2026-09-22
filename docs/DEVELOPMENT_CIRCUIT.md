# IET Development Circuit

## Purpose

IET is developed through a repository-first, phase-gated human + AI circuit.

The repository holds durable architecture, implementation, reports, and validation evidence. Chat history is useful for discussion but is not the long-term source of truth.

The default circuit is:

~~~text
Human owner + ChatGPT architecture/decision hub
→ repository canonical docs
→ one accepted roadmap phase
→ Codex / Work / another implementation agent
→ GitHub branch + commits + tests
→ phase report
→ human/ChatGPT review gate
→ next phase
~~~

Issue trackers such as Linear may manage live task state, but they do not replace the repository's canonical architecture or source history.

## Authority order

1. Human owner
2. Accepted architecture decisions / ADRs
3. `docs/PROJECT_COMPASS.md`
4. `docs/TARGET_ARCHITECTURE.md`
5. `docs/PRODUCTION_ROADMAP.md`
6. active phase contract, e.g. `docs/PHASE_01_INVITATION_ONBOARDING.md`
7. path-specific `.ai/rules/`
8. source code and tests for actual implemented behavior
9. phase reports / historical evidence
10. issue tracker/live planning metadata
11. chat summaries or legacy migrations

When two authoritative sources conflict materially, stop and resolve the contradiction before implementation.

## Canonical reading order for an agent

Before meaningful implementation:

1. read `AGENTS.md`;
2. read `.ai/rules/index.md` and matching rules;
3. read `docs/PROJECT_COMPASS.md`;
4. read `docs/CURRENT_STATE.md`;
5. read `docs/TARGET_ARCHITECTURE.md`;
6. read `docs/PRODUCTION_ROADMAP.md`;
7. read only the currently active phase contract;
8. read relevant ADRs;
9. inspect actual source/tests before proposing changes.

Do not read every future phase as implementation scope.

## Roles

### Human owner

- owns product intent;
- approves architecture changes;
- accepts phase gates;
- resolves ambiguous semantics;
- authorizes destructive/irreversible operations;
- decides when a phase is ready to merge/release.

### ChatGPT architecture/decision hub

- synthesizes current evidence and alternatives;
- maintains consistency between product vision and implementation;
- updates or proposes canonical docs when product decisions change;
- reviews phase results;
- identifies contradictions and hidden coupling;
- does not replace GitHub as source truth.

### Codex / implementation agent

- reads repository authority first;
- audits existing implementation before editing;
- changes only the accepted phase scope;
- writes tests;
- runs validation;
- records a durable implementation report;
- stops at the phase gate.

### ChatGPT Work

Work may be used instead of or alongside Codex for a substantial scoped phase, especially when multi-file repository inspection, browser workflows, connectors, or artifact production are useful.

The same repository/phase rules apply.

### Linear or another issue tracker

Optional live planning layer.

May hold:

- issue breakdown;
- dependencies;
- live status;
- assignees;
- links to commits/PRs.

It is not the architecture authority.

### GitHub

Authoritative for:

- files;
- commits;
- branches;
- pull requests;
- CI;
- review discussion;
- release history.

## One phase at a time

A phase contract defines:

- objective;
- accepted semantics;
- included scope;
- excluded scope;
- dependencies;
- acceptance criteria;
- tests;
- stop conditions.

An agent must not implement future phases merely because the target architecture describes them.

Example:

During Phase 1, do not build:

- Concepts;
- Profile;
- generic Submission engine;
- Planner;
- Ledger.

Phase 1 may leave compatible seams for later work, but no speculative database structure is required unless the active phase needs it.

## Implementation cycle

### 1. Synchronize

Before editing:

~~~text
git status --short
git branch --show-current
git fetch origin
git pull --ff-only <remote> <current-branch>
git rev-parse --short HEAD
~~~

If tracked local changes exist, preserve them before pulling. Do not reset, discard, or overwrite them casually.

### 2. Inspect

Inspect:

- active phase docs;
- source;
- migrations;
- policies;
- Actions;
- tests;
- applicable rules;
- relevant Laravel/package docs when version-specific behavior matters.

### 3. Report pre-change findings

For nontrivial phases, record:

- what currently exists;
- exact defects/gaps;
- proposed minimal architecture-compatible change;
- migrations/data risks;
- tests required.

If this reveals a frozen-boundary conflict, stop before coding.

### 4. Implement atomically

Prefer coherent commits.

Examples:

~~~text
feat(onboarding): ...
test(onboarding): ...
docs(onboarding): ...
~~~

Avoid one giant commit mixing unrelated cleanup.

### 5. Validate progressively

Start focused.

Then phase gate.

Typical final validation:

~~~text
php artisan test --compact
vendor/bin/phpstan analyse
vendor/bin/pint --dirty --format agent
npm run build
git status --short
~~~

Run additional concurrency/browser/manual checks when required by the phase.

Never report a command as passed unless it actually ran.

### 6. Write the phase report

Path:

`Development-CodexReports/<phase>-report.md`

Reports should contain:

- phase;
- baseline;
- objective;
- files inspected;
- issues discovered;
- changes made;
- migrations;
- tests;
- exact validation;
- manual checks;
- unresolved risks;
- Git commits;
- next recommended gate.

Do not store giant duplicated diffs in reports when Git already preserves them.

### 7. Review gate

Human/ChatGPT reviews:

- correctness;
- architecture consistency;
- UX;
- authorization;
- migration safety;
- test evidence;
- whether the phase exit gate is actually satisfied.

Only then activate the next phase.

## Git discipline

Current project reality takes precedence over an idealized branching model.

Rules:

- never develop directly on `main`;
- preserve the current accepted integration/feature branch strategy until a dedicated Git-flow decision changes it;
- use fast-forward pulls when synchronizing a clean branch;
- never force-push shared branches without explicit approval;
- never reset/discard local user work without explicit confirmation and backup;
- isolate unrelated work;
- use PRs for integration/release boundaries;
- tag production releases.

For the current accepted implementation baseline, the active branch is:

`feat/phase-05-content-context`

Future phase branching should be decided at each phase start from the then-current accepted integration baseline.

## Database discipline

- migrations are append-only once shared;
- no `migrate:fresh` assumption for production migration design;
- preserve immutable evidence;
- use explicit data migrations where semantics change;
- restrict deletes where history matters;
- test forward migration;
- document rollback limitations;
- use transactions/locks for race-sensitive transitions.

## AI behavior rules

An implementation agent must not:

- invent missing product semantics;
- silently weaken authorization;
- replace domain models with generic JSON merely to simplify code;
- introduce dependencies without approval;
- implement later roadmap phases;
- claim production readiness from tests alone;
- claim browser/manual validation it did not perform;
- rewrite a stable subsystem because a future name is cleaner;
- connect experimental financial instruments to external money.

## When to stop immediately

Stop and request review when:

- architecture docs conflict;
- a frozen boundary must change;
- a destructive migration appears necessary;
- user data might be lost;
- authorization implications are unclear;
- legal/financial product semantics would change;
- tests expose a deeper invariant contradiction;
- local repository state is unsafe to synchronize;
- required validation cannot run.

## Handoffs

Use `docs/handoffs/` for a task that spans agents/sessions and needs a concise operational state.

Do not create a new handoff for every small correction.

A handoff records:

- task/phase;
- current status;
- accepted decisions;
- work completed;
- Git state;
- validation;
- unresolved items;
- exact next step.

The canonical architecture remains in the main docs, not copied into every handoff.

## Prompting Codex

The normal prompt should be short because the repository now contains the long context.

Example:

~~~text
Work on IET Phase 1 only.

Read AGENTS.md, .ai/rules/index.md and matching rules, then:
- docs/PROJECT_COMPASS.md
- docs/CURRENT_STATE.md
- docs/TARGET_ARCHITECTURE.md
- docs/PRODUCTION_ROADMAP.md
- docs/PHASE_01_INVITATION_ONBOARDING.md

Inspect the current branch completely for the Phase 1 scope before changing code.
Do not implement later phases.
Preserve all frozen invariants.
Write/update Development-CodexReports/phase-01-invitation-onboarding-report.md.
Run all validation required by the phase.
Stop at the exit gate and report exact Git state and any unresolved risks.
~~~

This is preferred to pasting the whole architecture conversation into the prompt.
