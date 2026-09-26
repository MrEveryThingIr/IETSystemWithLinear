# M00 — Certified Foundation / Browser Baseline

## Status

**Remote gate complete. Owner browser gate pending.**

## Module objective

Establish the first browser-accepted checkpoint of the selective Ideal-v1 assembly before any feature module is revised or admitted.

This is a foundation-validation module. It adds no product capability.

## Accepted assembly behavior before browser review

Current assembly line:

~~~text
codex/ideal-v1-selective-assembly
~~~

Browser-gated process runtime checkpoint:

~~~text
SHA: 30dc939754c3fec7009250a61437db2633aabea7
CI:  36238246665
Result: success
~~~

Process-evidence documentation was then closed on the assembly at:

~~~text
SHA: dfdd6f46de6b8fd1ee06cdf0a9d5c5b1bee34763
CI:  36238610537
Result: success
Runtime/product behavior changed: none
~~~

This M00 review branch is based on that exact `dfdd6f46...` assembly head.

Remote proof:

- repository-wide Pint: **923 files passed**;
- PHPStan: **0 errors**;
- Blade compile/clear: passed;
- MySQL 8.4 migration portability: passed;
- SQLite migration/ops/backup-restore gates: passed;
- Vite production build: passed;
- PHPUnit: **552 passed / 3511 assertions**;
- npm audit: **0 vulnerabilities**;
- Composer audit: **no security vulnerability advisories found**.

## Historical source/evidence

- original accepted selective foundation: `891b333c49f166e61b9fa466e30742b3d70996c0`;
- S0 remote certification merge: `2b89e301ef7f167083445cd305847f83dbf3e048`;
- S0 post-merge CI: `36236888120`;
- S0 documentation closure: `707eaa621e267c31beaf3d9c71dde3c92178429e`;
- browser-gated process merge: `30dc939754c3fec7009250a61437db2633aabea7`;
- process post-merge CI: `36238246665`;
- process-evidence documentation head: `dfdd6f46de6b8fd1ee06cdf0a9d5c5b1bee34763`;
- documentation-head CI: `36238610537`.

## Authority map

M00 owns no domain truth. It validates that the current foundation can be trusted as the starting point.

It must not:

- create or modify business authority;
- change registration/accounting/temporal/planner semantics;
- alter migrations or dependencies;
- treat automated CI as browser proof.

## Dependencies

None beyond the accepted foundation itself.

## Consumers

Every later selective module consumes this checkpoint as its baseline.

## Wiring contract

There is no new domain wiring in M00.

The only process seam is:

~~~text
green accepted foundation
→ explicit owner browser acceptance
→ permission to begin M01 review/implementation
~~~

## Risk review

Primary risks:

- raw/missing translation keys;
- escaped Blade/source text rendered literally;
- broken navigation/header/menu rendering;
- RTL regressions;
- mobile navigation failure;
- form/component compile/render defects;
- accidental product behavior change from S0 formatter-only normalization.

## Proposed improvements

None are pre-approved in M00.

If browser inspection finds a defect, classify it as a baseline defect and fix only that defect on a dedicated correction branch with regression coverage where practical.

## Rollback

M00 itself adds documentation only. Product-runtime corrections, if any, receive their own correction branch/PR and can be reverted independently.

## Local sync and validation

Run from the existing Laragon project directory:

~~~bash
cd /c/laragon/www/EveryThing

git status --short
git fetch origin
git switch codex/ideal-v1-selective-assembly
git pull --ff-only origin codex/ideal-v1-selective-assembly
git status --short
git rev-parse HEAD

composer install --no-interaction --prefer-dist
php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

npm ci
npm run build

vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan test --compact
composer audit --locked --no-interaction
npm audit --audit-level=high

php artisan view:cache
php artisan view:clear
~~~

Never use `migrate:fresh` on the continuing acceptance database.

## Browser acceptance script

Use `IET_RELEASE_PROFILE=full`.

Start the application with the user's normal Laragon workflow, or:

~~~bash
php artisan serve
~~~

Then check:

1. English login/dashboard/navigation/profile representative pages.
2. Switch to Persian and confirm:
   - RTL layout;
   - selected calendar/timezone/date-time presentation is coherent;
   - no raw keys;
   - no literal Blade expressions/source.
3. Switch to Arabic and confirm RTL navigation/forms remain usable.
4. Switch to Simplified Chinese and confirm controls render localized rather than raw keys.
5. Open account menu/header/navigation and verify evaluated identity values.
6. Open representative forms and confirm labels/buttons/components render normally.
7. Test representative mobile width:
   - navigation opens/closes;
   - primary actions remain reachable;
   - no unusable overflow.
8. Keyboard/focus smoke on login/profile/navigation/forms.
9. Reload representative pages and confirm no state/rendering anomaly.
10. Confirm the S0 formatter-only normalization caused no observable product behavior change.

## Acceptance evidence

~~~text
Tested assembly SHA:
Local full tests:
Browser acceptance date:
Browser tester:
Locales checked:
Mobile checked:
Keyboard/focus checked:
Defects found:
Correction branches/commits:
Final accepted baseline SHA:
Result: PENDING
~~~

## Defect loop

For every release-blocking browser finding:

~~~text
finding
→ record exact route/state/locale
→ automated regression where practical
→ correction branch from assembly
→ focused tests
→ full remote CI
→ repeat affected browser checks
→ merge accepted correction
→ post-merge CI
→ update this report
~~~

## Next module

Only after explicit owner acceptance:

**M01 — Access, identity, registration provisioning, personal wallet/default monetary unit.**

M01 pre-planning will inspect the accepted assembly and relevant source commits from publication hardening without wholesale merging that branch.
