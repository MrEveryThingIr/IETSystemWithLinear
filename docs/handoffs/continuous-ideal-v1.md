# Continuous Ideal-v1 Handoff

## Mission

Complete the first publishable Ideal-v1 through **selective assembly**: preserve the accepted integrated foundation, admit later improvements only as independently reviewed/reversible modules, keep remote automated gates strict, and defer owner-local/browser acceptance to the cumulative worksheet.

## Read first

1. `AGENTS.md`
2. `.ai/rules/index.md` + matching rules
3. `docs/PROJECT_COMPASS.md`
4. `docs/CURRENT_STATE.md`
5. `docs/TARGET_ARCHITECTURE.md`
6. `docs/PRODUCTION_ROADMAP.md`
7. `docs/CONTINUOUS_REMOTE_EXECUTION.md`
8. `docs/EXAMPLE_STORY_WORLD.md`
9. `docs/handoffs/S0-selective-assembly-baseline.md`
10. the active selective-module report/handoff

## Current Git line

Accepted pre-selective integration foundation:

~~~text
integration/ideal-v1
891b333c49f166e61b9fa466e30742b3d70996c0
~~~

Selective assembly:

~~~text
codex/ideal-v1-selective-assembly
~~~

At creation, the assembly was exactly identical to `integration/ideal-v1` at `891b333`. Later planner/temporal/calendar/publication branches are **source libraries**, not merge units.

Active S0 review:

~~~text
codex/review-s0-baseline
PR #30 → codex/ideal-v1-selective-assembly
~~~

## Current objective — S0 only

Re-establish a certified baseline before admitting any feature.

S0 has already found one real baseline defect class: the original assembly CI was green but skipped repository-wide Pint because no PHP files differed from integration. The strengthened S0 gate scanned 923 PHP files and found 15 pre-existing style issues. Those were normalized by Pint in formatter-only commit `e57b29d2de68f97568187a19de60c9bbabf103ca`.

S0 also adds explicit Blade compilation to CI. Final branch/PR certification and post-merge assembly CI remain required before S1 may begin.

Owner-local/browser acceptance remains deferred to `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` under the standing continuous-remote authorization.

## Selective admission order

After S0 closes, proceed one independently reversible module at a time:

1. S1 — registration, wallet, default monetary unit.
2. S2 — shared temporal kernel.
3. S3 — permanent top status bar.
4. S4 — Planner lifecycle and evidence.
5. S5 — fractal calendar, split into read-only navigation, authorized projection, minute slots, creation prefill, then domain projections.
6. S6 — cross-system temporal consistency.
7. S7 — general UI/localization review.
8. S8 — flexible fields/form extensions only after v1-critical behavior is accepted.
9. S9 — release operations/publication.

Within each module: security/data-loss/authority correctness outranks convenience; lower-coupling and smaller independently testable changes go first.

## Admission contract

Every module records:

- source branch/commit and selected commits/files/hunks;
- behavior before and after;
- owned authority and authority it must not acquire;
- dependencies/consumers;
- persistence, authorization/privacy, locale/timezone/calendar/RTL/accessibility impact;
- focused and full validation;
- deferred browser acceptance;
- explicit deferrals and rollback method.

Unknown admission fields block that module, not unrelated modules.

## Git discipline

- Never develop directly on `main`, an RC, or `codex/ideal-v1-selective-assembly`.
- Create each review branch from the current assembly SHA.
- Fetch first; use fast-forward-only pulls locally.
- Do not merge aggregate candidate branches wholesale.
- One PR per independently reversible module.
- No force-push of shared history.
- Shared migrations stay append-only.
- Keep rejected candidate branches until stable release.
- Record source SHA, resulting assembly SHA, CI, browser evidence status, and rejected alternatives.

Current governance gap: the repository ruleset protects `main` only; the selective assembly branch is not yet server-side protected. Until repository settings are extended, preserve it by process and PR-only discipline.

## Persistent product rules

- Content is one independent versioned system; GroupSpace is one Context kind.
- Content has a home/origin Context for authoring/authorization but may be presented/referenced elsewhere.
- Normal presentation may follow current publication; evidence pins exact immutable revision/targets.
- Contexts compose modules; they do not duplicate Content, Planner, Accounting, Submission, or other kernels.
- Need/Offer is intent, not Match/Contract/obligation.
- Relationship is coordination, not Group Membership, Contract, ownership, employment, financing right, or payment.
- Conversation is collaboration evidence, not authoritative acceptance.
- Contract, Commitment, Fulfillment, obligation/settlement, and Accounting remain distinct authorities.
- Finance flows through explicit actions; balances are derived.
- User-facing UX stays plain while specialized kernels retain authority.
- Alice/Bob/Carol/Diego examples remain canonical across docs/tests.

## Interruption recovery

If interrupted:

1. inspect `codex/ideal-v1-selective-assembly` current SHA and CI;
2. inspect the active `codex/review-*` branch and its PR;
3. read the active S-module handoff/report;
4. never restart from chat memory or merge a candidate branch wholesale;
5. continue the current module until its remote gate is green or a genuine stop condition appears.
