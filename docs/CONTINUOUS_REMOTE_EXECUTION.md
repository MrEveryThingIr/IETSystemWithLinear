# Continuous Remote Roadmap Mode

## Authorization

The human owner has explicitly authorized continuous remote implementation of the accepted Ideal-v1 roadmap.

Local Laragon/browser acceptance is deferred until the integrated roadmap is complete or a true stop condition is reached.

This changes **when** human local acceptance happens. It does not weaken automated quality gates.

## Integration trunk

~~~text
integration/ideal-v1
~~~

The trunk was rooted at the last accepted pre-AI/manual baseline:

~~~text
2c7a5c35a31fe86d761a1cafd189560bec220784
~~~

The office Access Invitation / Intent Registry work is being rebuilt onto that baseline without the currently unused AI-assistance and Development-Origin runtime layers.

## Remote cycle

For every milestone:

~~~text
integration/ideal-v1
        ↓ branch
feat/ideal-v1-<milestone>
        ↓
implementation
migrations where justified
tests
System Manual source
canonical docs
phase report
        ↓
remote CI green
        ↓
integration checkpoint
        ↓
next milestone
~~~

A milestone may proceed without owner-local/browser acceptance when all remote gates are green and no stop condition is present.

## Required remote gate

At minimum, when applicable:

- focused PHPUnit;
- full PHPUnit;
- PHPStan;
- Pint on changed PHP;
- Vite production build;
- migration forward + rollback/reapply smoke;
- scheduler/database-queue smoke;
- backup/restore smoke;
- npm audit;
- Composer audit;
- authorization and privacy regression tests;
- concurrency/idempotency tests for race-sensitive transitions.

Never report a gate as passed unless GitHub Actions or another actually executed command proves it.

## Checkpoints

Each milestone must leave:

- a coherent feature branch;
- atomic commits;
- a durable contract/report;
- exact integration commit SHA;
- exact CI run;
- migration list;
- known limitations;
- deferred browser checks;
- an entry in `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

Where repository tooling permits, create immutable development checkpoint tags. If tag creation is not available to the active agent, record the exact immutable SHA and do not pretend a tag exists.

## Integration rules

- never develop directly on `main`;
- no force-push of shared history;
- no destructive rewrite of earlier checkpoint history;
- append-only shared migrations;
- no `migrate:fresh` assumption;
- integration is cumulative;
- fixes discovered later are new commits/milestones rather than rewriting historical checkpoints;
- a later feature may consume earlier domains but must not silently redefine their authority.

## Documentation-as-Content rule

Every user-facing milestone updates both:

1. repository developer/architecture docs;
2. `App\Support\SystemManualContent`, which is the canonical source materialized as normal versioned IET Content.

The System Manual must teach exact interaction, not just concepts:

~~~text
WHO
→ WHERE in UI
→ WHAT user sees
→ WHAT to click/select/type
→ WHAT durable result is created
→ WHO can see it
→ WHAT it does not imply
~~~

Use `docs/EXAMPLE_STORY_WORLD.md` for consistent Alice/Bob/Carol/Diego examples.

## Stop conditions

Continuous mode pauses only when:

- canonical architecture sources materially contradict each other;
- a destructive/data-losing migration appears necessary;
- authorization/legal/financial semantics are genuinely ambiguous and cannot be resolved from accepted docs;
- required dependencies or credentials need explicit owner approval;
- remote validation infrastructure cannot prove the milestone safe enough to build upon;
- a change would connect experimental finance to real custody/external-money movement;
- repository permissions/tooling make the required safe Git operation impossible.

Normal implementation defects are not stop conditions: fix them, add regression coverage, and continue.

## Final human gate

After the roadmap is integrated remotely:

1. owner backs up the local database;
2. follows `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` checkpoint by checkpoint;
3. migrates forward only;
4. runs focused/full gates;
5. follows the Alice/Bob/Carol/Diego browser story from invitation to the latest implemented domain;
6. records defects;
7. defects are fixed on the current integration/release line with regression tests;
8. release candidate is frozen only after human acceptance.
