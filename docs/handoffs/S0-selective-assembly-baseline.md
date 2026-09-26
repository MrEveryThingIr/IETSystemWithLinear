# S0 Selective Assembly Baseline

## 1. Task identifier and title

**S0 — Re-establish the accepted baseline for selective Ideal-v1 assembly**

## 2. Current workflow status

In progress — remote baseline certification is being strengthened and re-run on a dedicated review branch.

## 3. Responsible arm or decision gate, and next step

- Responsible arm: ChatGPT/GitHub remote execution.
- Decision gate: S0 baseline certification.
- Next step: require a green CI run on this branch, open a PR into `codex/ideal-v1-selective-assembly`, require PR-context CI, merge only the S0 tooling/evidence changes, then verify the post-merge assembly CI.
- Owner-local browser smoke: Pending.

## 4. Objective

Prove the exact accepted foundation before any selective feature admission, so later regressions are attributable to the admitted module rather than an existing baseline defect.

## 5. Authoritative inputs

- Assembly branch: `codex/ideal-v1-selective-assembly`.
- Accepted integration baseline: `891b333c49f166e61b9fa466e30742b3d70996c0`.
- `integration/ideal-v1` and the assembly branch are identical at that SHA.
- Existing release authority: `docs/PUBLISHABLE_V1_RELEASE_GATE.md`.
- Existing collaboration authority: `docs/DEVELOPMENT_CIRCUIT.md` and `docs/handoffs/README.md`.

## 6. Accepted decisions and invariants

- S0 changes no application/domain behavior.
- Aggregate temporal/planner/publication candidate branches are not merged as part of S0.
- The baseline must prove SQLite and MySQL migration viability, formatting, static analysis, Blade compilation, frontend build, tests, and dependency audits.
- Owner-local rendered-browser acceptance remains a separate explicit gate; automated success must not be represented as visual/browser proof.

## 7. Included and excluded scope

Included:

- verify exact assembly/integration SHA equality;
- inspect the existing CI contract;
- strengthen baseline/release CI so repository-wide Pint cannot be silently skipped;
- add explicit Blade compilation;
- record exact remote evidence.

Excluded:

- registration/accounting changes;
- temporal kernel changes;
- Planner lifecycle/evidence changes;
- calendar changes;
- UI feature changes;
- flexible form work;
- migrations, dependencies, or production configuration changes.

## 8. Files and systems examined

- `.github/workflows/ci.yml`
- `composer.json`
- `package.json`
- `docs/PUBLISHABLE_V1_RELEASE_GATE.md`
- `docs/handoffs/README.md`
- `docs/handoffs/continuous-ideal-v1.md`
- GitHub Actions run `36235097202` on the original assembly SHA.

## 9. Changes made

On `codex/review-s0-baseline`:

1. CI now runs repository-wide `vendor/bin/pint --test` for pushes to the selective assembly, S0 review branches, and release branches.
2. Ordinary feature/PR work retains changed-PHP-file Pint behavior for efficiency.
3. CI explicitly compiles and clears Blade views with `php artisan view:cache` and `php artisan view:clear`.
4. This handoff records S0 evidence and remaining manual acceptance.

No application code, database schema, dependency, localization catalog, or domain configuration is changed.

## 10. Commands and tests actually run, with exact results

Original assembly run before the S0 CI strengthening:

- GitHub Actions run: `36235097202`.
- SHA: `891b333c49f166e61b9fa466e30742b3d70996c0`.
- Overall result: success.
- PHPUnit: **552 passed / 3511 assertions**.
- PHPStan: **0 errors**.
- SQLite application migration/setup: passed.
- MySQL 8.4 migration portability smoke: passed.
- SQLite rollback/reapply, scheduler, queue, backup/restore smoke: passed.
- Vite production build: passed.
- npm audit: **0 vulnerabilities**.
- Composer audit: **no security vulnerability advisories found**.
- Repository-wide Pint: **not proven by this run**; the old workflow skipped Pint because no PHP files differed from integration.
- Explicit Blade compilation: **not present in the old workflow**.

Current S0 review-branch CI: Pending after this commit.

Owner-local browser smoke in English, Persian, Arabic, and Simplified Chinese: Pending.

## 11. Risks and unresolved questions

- The baseline is not fully S0-certified until repository-wide Pint and explicit Blade compilation pass on the new review branch.
- Automated tests cannot substitute for the owner-local rendered-browser/RTL smoke.
- The Vite build emits the existing optional Fontaine optimized-fallback warning; it is non-fatal and does not currently fail the build.

## 12. Branch, commits, pull request, and Linear issue

- Baseline/assembly SHA: `891b333c49f166e61b9fa466e30742b3d70996c0`.
- Review branch: `codex/review-s0-baseline`.
- CI-hardening commit: recorded in GitHub history on this branch.
- Pull request: Not created yet.
- Linear issue: Not created / not available in this execution context.

## 13. Review findings

The original assembly CI was substantially green but insufficient for the literal S0 contract because repository-wide Pint was skipped on the no-diff assembly branch and Blade compilation was not an explicit step. This review branch closes those two remote-certification gaps without changing product behavior.

## 14. Final outcome

Pending.

S0 may be marked remotely certified only after the strengthened branch CI, PR-context CI, and post-merge assembly CI all pass. Owner-local browser smoke remains explicitly pending until performed.

## 15. Prompt for next step

Verify the new review-branch CI. If green, open a PR into `codex/ideal-v1-selective-assembly`; verify PR CI; merge the S0 tooling/evidence only; verify post-merge assembly CI; update this handoff with exact run/commit/PR evidence. Do not begin S1 feature admission before S0 is recorded.
