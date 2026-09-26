# Planner / Temporal hardening integration candidate

Branch: `integration/ideal-v1-planner-temporal-candidate` from `891b333c49f166e61b9fa466e30742b3d70996c0`. This is **not** a release acceptance record.

## Integrated selectively

- Existing `PlanOccurrence` remains the schedule/execution source of truth. Window state is derived from its immutable scheduled start/end and start/end window fields; no calendar data store or new financial, Contract, or Fulfillment authority was introduced.
- Starting a scheduled occurrence is allowed from the early-window start through the late-window end, inclusive. A missed window does not automatically transition status, record an actual timestamp, or affect existing evidence. Completion requires an explicit recorded start; actual timestamps reflect the user actions, not the schedule.
- The plan page presents scheduled versus actual time, its timezone-aware execution window and readiness, and only offers applicable lifecycle actions. Stale UI requests still recheck the locked occurrence and report validation errors.
- Same-Context evidence can be selected or uploaded directly on an occurrence, subject to the existing Context submission policy and the existing asset pipeline. The selector has a no-available-evidence explanation. Existing menu/navigation and ambient localization are untouched; new planner copy is present in English, Persian, Arabic and Chinese.
- Focused tests were added for early/ready/late/missed transitions and direct upload. Mobile layout remains single-column until the existing responsive breakpoints.

## Deferred / unresolved

- The calendar year/month/day/hour drill-down, date/minute seeded creation and detailed day evidence cards on `release/ideal-v1-rc-7-hardening` and `fix/rc-planner-execution-calendar-hardening` overlap in `Planner/Index.php` and its Blade view and were **not** copied wholesale. Existing Today/List/Calendar behavior remains on the integration baseline; reconcile and test the authorized result cap, valid selected dates, timezone boundaries and mobile/RTL before promotion.
- This remote editing environment lacks PHP, Composer dependencies and browser runtime; no PHPUnit, Pint, PHPStan, Blade compilation, Vite build or browser/mobile checks were run here. Do not treat this as a passing release gate.
- Confirm direct-upload transactions, asset eligibility and authorization under race/revocation conditions on a full local environment. Check the local browser on a phone-width viewport and desktop with all four locales.

## Local validation before promotion

```sh
composer install --no-interaction
npm ci
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/PlannerExecutionWindowTest.php tests/Feature/PlannerEvidenceUploadTest.php tests/Feature/PlannerKernelTest.php tests/Feature/PlannerExperienceTest.php tests/Feature/CommitmentFulfillmentKernelTest.php tests/Feature/CommitmentFulfillmentExperienceTest.php tests/Feature/LocalizationParityTest.php tests/Feature/ReleaseExperienceTest.php
vendor/bin/phpstan analyse --no-progress
php artisan view:cache
npm run build
```

Manual: inspect Planner Today/List/Calendar and occurrence evidence on mobile and desktop in English/Persian/Arabic/Chinese, including RTL, unauthorized contexts, empty evidence selector, and DST-crossing plans. Preserve the baseline mobile menu while integrating further calendar work.
