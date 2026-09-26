# Continuous Ideal-v1 Handoff

## Mission

Complete the first publishable Ideal-v1 through **browser-gated selective assembly**.

The project is not rebuilt from scratch. Existing accepted code and later candidate branches are source libraries. One module is inspected, selectively improved, remotely validated, browser-accepted, corrected if necessary, and only then merged into the cumulative assembly.

## Read first

1. `AGENTS.md`
2. `.ai/rules/index.md` + matching rules
3. `docs/PROJECT_COMPASS.md`
4. `docs/CURRENT_STATE.md`
5. `docs/TARGET_ARCHITECTURE.md`
6. `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`
7. `docs/PRODUCTION_ROADMAP.md`
8. `docs/CONTINUOUS_REMOTE_EXECUTION.md`
9. `docs/EXAMPLE_STORY_WORLD.md`
10. active module report/handoff

## Current Git line

Selective assembly:

~~~text
codex/ideal-v1-selective-assembly
~~~

Initial accepted pre-selective foundation:

~~~text
891b333c49f166e61b9fa466e30742b3d70996c0
~~~

S0 certified runtime checkpoint:

~~~text
PR #30 merged
runtime/checkpoint: 2b89e301ef7f167083445cd305847f83dbf3e048
post-merge CI: 36236888120
result: 552 tests / 3511 assertions
Pint: 923 files
PHPStan/Blade/MySQL/SQLite/ops/Vite/npm/Composer: green
~~~

The assembly later received S0 documentation-only closure at `707eaa621e267c31beaf3d9c71dde3c92178429e` with CI `36237332774` green.

Browser-gated process checkpoint:

~~~text
PR #32 merged
assembly SHA: 30dc939754c3fec7009250a61437db2633aabea7
post-merge CI: 36238246665 — success
runtime changes: none (governance/docs only)
~~~

## Current objective — M0/S0 browser baseline

Remote S0 is complete, but the owner has now required browser acceptance **before each module is finally assembled**.

Because that rule was adopted after S0 had already merged, M1 implementation is blocked until the owner browser-smokes the current assembly baseline.

Use the S0 section of `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

If a defect is found:

~~~text
assembly head
→ dedicated correction branch
→ automated reproduction where practical
→ correction
→ remote CI
→ repeat affected browser checks
→ PR merge
→ post-merge CI
→ update S0 browser evidence
~~~

Only after explicit S0 browser acceptance begin M1.

## Active module order

The authoritative dependency-aware order is in `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`.

Summary:

1. M0 — certified foundation/browser baseline.
2. M1 — access, identity, registration provisioning, wallet/default monetary unit.
3. M2 — shared temporal/localization presentation kernel.
4. M3 — Context, Content, Assets, immutable Evidence.
5. M4 — Groups, Membership, Admissions, Group Agreements.
6. M5 — Planner + fractal calendar.
7. M6 — Personal Accounting.
8. M7 — Profile Intent, Need/Offer, matching.
9. M8 — Relationship, Conversation, Timeline.
10. M9 — Proposal, negotiation, Contract/ContractVersion.
11. M10 — Commitment + Fulfillment.
12. M11 — Financial Obligation + Settlement + Accounting bridge.
13. M12 — Structured Interaction, Submission, Evaluation.
14. M13 — Notifications/realtime + Home/Today.
15. M14 — Domain/Business Blueprints and composed journeys.
16. M15 — cross-system consistency, flexible extensions, polish, operations, release.

Historical S-plan mapping is preserved in the roadmap; it is not discarded.

## Module admission contract

Each module remains on its review branch until explicit browser acceptance.

Every living module report records:

- objective and current behavior;
- source branches/commits/files/hunks;
- authority/dependency/consumer maps;
- wiring contract;
- persistence/migration/authorization/privacy/temporal/money/locale/accessibility risks;
- required/recommended/deferred improvements;
- rollback;
- remote tests;
- browser script;
- defects/fixes;
- exact accepted head;
- merge/post-merge CI;
- next-module wiring notes.

Preserve the original pre-plan. Append actual decisions rather than replacing history with a hindsight-only summary.

## Git discipline

- Never develop directly on `main`, an RC, or `codex/ideal-v1-selective-assembly`.
- Branch each module from the current accepted assembly head.
- Candidate branches are source libraries, not merge units.
- One independently reversible PR per module or intentionally separated submodule.
- Keep the PR open/draft through browser review.
- Merge only the exact browser-accepted head.
- No force-push of accepted shared history.
- Shared migrations remain append-only.
- Never use `migrate:fresh` on the owner's continuing acceptance database.
- Keep rejected candidates until stable release.

Current governance gap: GitHub server-side rules protect `main`, not the assembly branch. PR-only discipline therefore remains mandatory.

## Persistent product rules

- User authentication and Actor participation are distinct.
- Platform authority, Group Membership and Context access are distinct.
- Content is one independent versioned system with home Context + authorized placement/reference.
- Evidence pins exact immutable publication targets.
- Need/Offer is intent; matching creates no obligation.
- Relationship coordinates people but is not Contract/ownership/employment/financing authority.
- Conversation text is collaboration evidence, not acceptance.
- Group Agreement and party-specific negotiated Contract are distinct.
- Contract, Commitment, Fulfillment, Financial Obligation/Settlement and Accounting remain separate authorities.
- Financial truth flows through explicit Actions; balances are derived.
- Planner schedule/completion never silently becomes Contract or financial truth.
- Realtime is transport, not authority.
- Alice/Bob/Carol/Diego examples remain canonical.

## Interruption recovery

If interrupted:

1. inspect current assembly SHA and CI;
2. inspect current review branch/PR;
3. read `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`;
4. read the active living module report;
5. inspect latest browser-acceptance status in the worksheet/report;
6. do not start the next module unless the current browser gate is accepted;
7. never resume from chat memory or merge an aggregate candidate wholesale.
