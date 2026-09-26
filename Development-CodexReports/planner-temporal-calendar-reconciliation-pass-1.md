# Planner temporal/calendar reconciliation — first pass

Base: `4e553fe30391e336b4d202d74aae9742f5fd7528` on `integration/ideal-v1-temporal-calendar-reconcile`. Reference: `5830cb44e09c0238f1f1591f0134419ba67834c3` on `codex/release-first-publication-hardening`. This is a selective implementation, not a merge or release acceptance record.

## Included

- A reusable, profile-calendar-aware temporal presentation helper resolves month/year boundaries and day/month/year/time labels using PHP Intl; all stored dates and instants stay in the original Gregorian/UTC Planner kernel.
- Planner Calendar drills from selected calendar year → month → local day → hour → minute slots (60/30/15/5/1). Both desktop and phone-sized month layouts offer access to the day view. Items link to the existing authorized Plan/Occurrence, not a duplicate event store. Today/List remain derived from the same Plan Occurrences.
- Query results are filtered through Plan view policy before the 500-occurrence/150-plan display cap; a Context filter also applies before retrieval. There is no policy bypass via URL-selected dates or Contexts. This is a correctness improvement, not a claim that unbounded year reads scale well.
- The creation form accepts only valid Gregorian local date, 24-hour minute, and bounded integer duration query seeds. A seed prepopulates the existing form only: it does not create a Plan, change frequency, infer a Contract, or bypass Context authorization. Existing blueprint frequency/duration defaults remain intact unless a valid explicit slot duration is passed.
- Existing candidate occurrence start-window/readiness and same-Context evidence selection/upload behavior remain untouched. Existing localization/menu files remain untouched; new calendar wording is isolated in four language catalogs.
- Added focused feature coverage for Persian calendar boundaries, navigation and immutable scheduled truth, valid/invalid seeds, and authorization/invalid drill-down inputs.

## Deferred and limitations

- No generalized conversion of other screens, shared frontend date controls, or System Manual source is included in this first pass; the latter must be synchronized before release acceptance. Planner create still uses the existing Gregorian-native date inputs. The selected calendar is a presentation view; do not interpret displayed calendar labels as storage dates.
- Year view loads candidate rows before filtering in memory, so large installations need an indexed, policy-safe retrieval strategy; no hidden occurrence is rendered, but this is not a scale gate. The 500-item display cap can truncate a busy year.
- Repeated/nonexistent local clock hours at DST transitions need dedicated browser acceptance. Month view shows one local day per civil date; clicking a slot never automatically creates an occurrence.
- PHP/Composer/vendor dependencies and browser runtime are not available in this editing environment. No PHPUnit, Pint, PHPStan, Blade compile, migration smoke, Vite build, or phone-width/RTL browser check has been run. `git diff --check 4e553fe HEAD` completed without whitespace errors; do not treat this as CI success.

## Required validation on a complete checkout

```sh
composer install --no-interaction
npm ci
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/PlannerCalendarReconciliationTest.php tests/Feature/PlannerExecutionWindowTest.php tests/Feature/PlannerEvidenceUploadTest.php tests/Feature/PlannerExperienceTest.php tests/Feature/TemporalLocalizationTest.php
vendor/bin/phpstan analyse --no-progress
php artisan view:cache
npm run build
```

Before promotion, run full project CI and the migration/operations gates from `docs/CONTINUOUS_REMOTE_EXECUTION.md`. In the owner browser, review year/month/day/hour/minute navigation on phone and desktop in en/fa/ar/zh_CN, with RTL, Gregorian/Persian/Hijri preferences, DST boundaries, unauthorized Contexts, and the original evidence/start-window behavior. Add the exact deferred browser results to `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` and synchronize the System Manual as part of the release gate.
