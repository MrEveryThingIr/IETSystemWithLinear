# S0 Selective Assembly Baseline

## 1. Task identifier and title

**S0 — Re-establish the accepted baseline for selective Ideal-v1 assembly**

## 2. Current workflow status

In progress — baseline defects have been classified and repaired on the dedicated S0 review branch. Final branch/PR certification and post-merge assembly certification remain.

## 3. Responsible arm or decision gate, and next step

- Responsible arm: ChatGPT/GitHub remote execution.
- Decision gate: S0 baseline certification.
- Review branch: `codex/review-s0-baseline`.
- Pull request: **#30 — S0: certify selective assembly baseline** (draft while final gates run).
- Next step: obtain green full push + PR-context CI on the final branch head, merge PR #30 into `codex/ideal-v1-selective-assembly`, then require a green post-merge assembly CI.
- Owner-local/browser acceptance is intentionally deferred to `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` under the current continuous-remote-development authorization.

## 4. Objective

Prove the exact accepted foundation before any selective feature admission, so later regressions are attributable to the admitted module rather than an existing baseline defect.

## 5. Authoritative inputs

- Assembly branch: `codex/ideal-v1-selective-assembly`.
- Accepted integration baseline: `891b333c49f166e61b9fa466e30742b3d70996c0`.
- `integration/ideal-v1` and the assembly branch were verified identical at that SHA.
- Existing release authority: `docs/PUBLISHABLE_V1_RELEASE_GATE.md`.
- Existing continuous execution authority: `AGENTS.md`, `docs/CONTINUOUS_REMOTE_EXECUTION.md`, `docs/DEVELOPMENT_CIRCUIT.md`, and `docs/handoffs/README.md`.
- Selective-assembly admission contract supplied by the owner: S0 must precede S1 and later modules.

## 6. Accepted decisions and invariants

- S0 changes no application/domain semantics.
- Aggregate temporal/planner/publication candidate branches are not merged as part of S0.
- The baseline must prove SQLite and MySQL migration viability, repository formatting, static analysis, Blade compilation, frontend build, full tests, and dependency audits.
- A defect exposed by the stronger S0 gate is classified as a baseline defect and corrected here rather than attributed to a future admitted module.
- Owner-local rendered-browser acceptance remains explicit but deferred; automated success is never represented as visual/browser proof.
- The temporary write-enabled formatter workflow used to repair baseline style drift must not remain in the finished branch.

## 7. Included and excluded scope

Included:

- verify exact assembly/integration SHA equality;
- inspect and strengthen the existing CI contract;
- ensure assembly/S0/release checkpoints run repository-wide Pint;
- explicitly compile Blade views;
- classify and repair pre-existing formatter drift;
- record exact remote evidence and deferred browser checks.

Excluded:

- registration/accounting feature changes;
- temporal kernel changes;
- Planner lifecycle/evidence changes;
- calendar changes;
- UI feature changes;
- flexible-form work;
- migrations, dependencies, localization behavior, or production configuration changes.

## 8. Files and systems examined

- `.github/workflows/ci.yml`
- `composer.json`
- `package.json`
- `AGENTS.md`
- `.ai/rules/index.md`
- `.ai/rules/app.md`
- `.ai/rules/seeders.md`
- `docs/PUBLISHABLE_V1_RELEASE_GATE.md`
- `docs/DEVELOPMENT_CIRCUIT.md`
- `docs/CONTINUOUS_REMOTE_EXECUTION.md`
- `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`
- `docs/handoffs/README.md`
- `docs/handoffs/continuous-ideal-v1.md`
- GitHub Actions and repository rulesets.

## 9. Changes made

On `codex/review-s0-baseline`:

1. CI now runs repository-wide `vendor/bin/pint --test` for pushes to the selective assembly, S0 review branches, and release branches.
2. Ordinary feature/PR work retains changed-PHP-file Pint behavior for efficiency.
3. CI explicitly compiles and clears Blade views with `php artisan view:cache` and `php artisan view:clear`.
4. The stronger gate exposed 15 pre-existing formatter violations across the 923-file Pint scan.
5. A one-run temporary workflow executed the repository's Pint formatter and committed only the 15 identified PHP files as `e57b29d2de68f97568187a19de60c9bbabf103ca` (`style: normalize S0 baseline with Pint`).
6. That temporary write-enabled workflow was immediately removed in `eeebe5aa099cbe875096851d4a1a9b980c9eef20`; it is not part of the intended finished CI surface.
7. Durable S0 report/worksheet/handoff state is synchronized.

The 15 PHP-file changes are formatter-only baseline normalization. No domain behavior, schema, dependency, or localization contract is intentionally changed.

## 10. Commands and tests actually run, with exact results

### Original accepted assembly

GitHub Actions run `36235097202` on `891b333c49f166e61b9fa466e30742b3d70996c0`:

- overall result: success;
- PHPUnit: **552 passed / 3511 assertions**;
- PHPStan: **0 errors**;
- SQLite application migration/setup: passed;
- MySQL 8.4 migration portability smoke: passed;
- rollback/reapply, scheduler, queue, SQLite backup/restore smoke: passed;
- Vite production build: passed;
- npm audit: **0 vulnerabilities**;
- Composer audit: **no security vulnerability advisories found**;
- repository-wide Pint: **not proven** because the old workflow skipped Pint when no PHP files differed from integration;
- explicit Blade compilation: **not present** in the old workflow.

### S0 strengthened gate before formatter repair

Push CI run `36235989905` on `439137262c9c7cf04f71b7fec32857521ac8211e`:

- MySQL, npm audit, and Vite passed before the failing step;
- repository-wide Pint scanned **923 files** and found **15 style issues**;
- run correctly failed at formatting and skipped later steps;
- this established a pre-existing baseline defect rather than a candidate regression.

PR-context CI run `36236060430` on the same head succeeded, including the new explicit Blade compilation step, but PR-context Pint still uses changed-file scope by design and therefore did not substitute for the failed repository-wide S0 certification.

### Formatter-only baseline repair

- Temporary formatter workflow run: `36236219527` — success.
- Formatter commit: `e57b29d2de68f97568187a19de60c9bbabf103ca`.
- Exact repair set: the same 15 PHP files reported by the failed Pint gate.
- Temporary formatter workflow removal: `eeebe5aa099cbe875096851d4a1a9b980c9eef20`.

### Final certification

- Final S0 branch push CI: Pending on the final documentation head.
- Final PR-context CI: Pending on the final documentation head.
- Post-merge assembly CI: Pending until PR #30 merges.

### Deferred owner-local/browser acceptance

English, Persian, Arabic, Simplified Chinese, RTL, mobile, keyboard/focus, and representative release-journey browser checks are recorded in `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` and intentionally deferred under continuous remote mode.

## 11. Risks and unresolved questions

- S0 is not remotely certified until the final branch/PR gates and post-merge assembly gate are green.
- Automated tests cannot substitute for owner-local rendered-browser/RTL acceptance.
- The Vite build emits the existing optional Fontaine optimized-fallback warning; it is non-fatal.
- Repository ruleset `Protect main` protects `main` only. The selective assembly branch is currently not covered by an equivalent GitHub ruleset. Until repository settings are updated, process discipline—not server-side protection—prevents direct/force/deletion mistakes on the assembly branch.

## 12. Branch, commits, pull request, and Linear issue

- Baseline/assembly SHA: `891b333c49f166e61b9fa466e30742b3d70996c0`.
- Review branch: `codex/review-s0-baseline`.
- CI hardening commit: `8916731c7628436a019ddf7f0267c2de7c3497e8`.
- Initial S0 evidence commit: `439137262c9c7cf04f71b7fec32857521ac8211e`.
- Temporary repair workflow commit: `dd211437ebf510fd4f7fd4a2bac0767ddfa29ccf`.
- Formatter-only repair commit: `e57b29d2de68f97568187a19de60c9bbabf103ca`.
- Temporary workflow removal commit: `eeebe5aa099cbe875096851d4a1a9b980c9eef20`.
- Pull request: **#30**.
- Linear issue: Not created / not available in this execution context.

## 13. Review findings

The original assembly was functionally green under its existing CI but did not satisfy the literal S0 certification contract. The strengthened gate found real repository-wide style drift that prior changed-file-only CI could not see. S0 therefore correctly classified and repaired that debt before any S1 feature admission.

The new explicit Blade compile gate passed in PR context. The repository also lacks server-side protection for the selective assembly branch, which remains a release-governance follow-up.

## 14. Final outcome

**Pending final remote certification.**

S0 becomes remotely certified only after:

1. final review-branch push CI is green with repository-wide Pint;
2. final PR-context CI is green;
3. PR #30 is merged through GitHub into the selective assembly;
4. post-merge assembly CI is green.

Owner-local/browser acceptance remains deferred and separately recorded; it is not silently treated as complete.

## 15. Prompt for next step

Finish the final S0 branch/PR gates, merge PR #30 if green, verify post-merge assembly CI, and record the exact assembly SHA/run. Only then begin S1 — registration, wallet, and default monetary unit — from the new assembly head.
