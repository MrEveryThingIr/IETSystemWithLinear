# S0 Selective Assembly Baseline — Remote Certification Report

## Status

**In progress: final branch/PR certification and post-merge assembly certification pending.**

## Purpose

S0 proves the accepted Ideal-v1 foundation before any selective feature admission. It is deliberately non-feature work.

## Baseline identity

- Accepted integration: `integration/ideal-v1`
- Selective assembly: `codex/ideal-v1-selective-assembly`
- Shared starting SHA: `891b333c49f166e61b9fa466e30742b3d70996c0`
- Initial comparison: identical, ahead 0 / behind 0, no changed files

## Original evidence

Assembly CI run `36235097202` succeeded with:

- 552 PHPUnit tests / 3511 assertions;
- PHPStan 0 errors;
- SQLite setup/migrations;
- MySQL 8.4 migration portability;
- rollback/reapply + scheduler/database-queue smoke;
- SQLite backup/restore smoke;
- Vite production build;
- npm audit with 0 vulnerabilities;
- Composer audit with no vulnerability advisories.

Two S0 certification gaps were found in the workflow itself:

1. repository-wide Pint was skipped on the no-diff assembly branch;
2. Blade compilation was not an explicit step.

## CI correction

S0 CI hardening commit `8916731c7628436a019ddf7f0267c2de7c3497e8`:

- runs repository-wide `vendor/bin/pint --test` for assembly, S0, and release pushes;
- retains changed-file Pint for ordinary feature/PR work;
- explicitly executes `php artisan view:cache` then `php artisan view:clear`.

## Baseline defect discovered

Strengthened push run `36235989905` failed correctly at repository-wide Pint:

~~~text
923 files, 15 style issues
~~~

The issues were pre-existing at the accepted baseline and therefore belong to S0, not to a later selective module.

The affected files were limited to existing Content/Group code, one opt-in demo seeder, and related tests. No functional failure was reported before the Pint step.

## Repair

A temporary, branch-scoped GitHub Actions workflow ran the repository formatter once.

- repair workflow run: `36236219527` — success;
- formatter-only commit: `e57b29d2de68f97568187a19de60c9bbabf103ca`;
- changed set: exactly the 15 files identified by the failed Pint scan;
- temporary workflow removed in `eeebe5aa099cbe875096851d4a1a9b980c9eef20`.

The temporary workflow is not intended to survive into the assembly branch.

## Pull request

- PR #30: `S0: certify selective assembly baseline`
- base: `codex/ideal-v1-selective-assembly`
- head: `codex/review-s0-baseline`
- kept draft while final gates are incomplete.

Earlier PR-context run `36236060430` passed and proved the explicit Blade compilation step. Final PR-context proof must still run on the complete S0 head.

## Governance finding

Repository ruleset `Protect main` currently targets the default/`main` branch only. The selective assembly branch has no equivalent server-side ruleset yet.

This does not alter application behavior, but it is a release-process risk. Until settings are extended, agents must treat assembly as PR-only, never force-push it, and never delete/rewrite accepted checkpoints.

## Deferred local/browser acceptance

The owner has authorized continuous remote development. Therefore S0 records, but does not falsely claim, browser acceptance.

The cumulative worksheet contains the S0 local commands and representative English/Persian/Arabic/Simplified-Chinese, RTL, mobile, and keyboard/focus checks.

## Remaining closure gate

S0 closes only when:

1. final branch push CI is green, including repository-wide Pint;
2. final PR-context CI is green;
3. PR #30 merges into the assembly;
4. post-merge assembly CI is green;
5. exact resulting assembly SHA/run are recorded.

No S1 feature admission begins before those remote conditions are met.
