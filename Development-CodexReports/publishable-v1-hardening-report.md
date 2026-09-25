# Publishable v1 Hardening Report

## Objective

Convert the cumulative Ideal-v1 line into a bounded publishable release candidate for real-user learning, while deferring AI Copilot and speculative post-v1 abstraction.

## Inherited defects closed in this checkpoint

- reserved-email Access Invitations are server-enforced single-use; unreserved links retain bounded multi-use;
- Intent Directory visibility is applied in SQL before ordering/pagination instead of filtering a hard 200-row window in PHP;
- Directory uses real pagination and resets it when filters change;
- useful Directory filters persist in the URL;
- create → Directory `highlight` is consumed with focusable visual emphasis;
- Profile Intent management now exposes the subject, arrangement, cash range, currency/basis, exchange preference and negotiation notes supported by the guided Intent journey;
- regression tests cover the >200 hidden-record authorization case, highlight handoff, invitation semantics, consumed reserved-link reuse and complete Profile value-model editing;
- CI Pint scope now derives from the actual PR base or `integration/ideal-v1` instead of the historical `feat/group-spaces-communication` branch;
- cumulative release/acceptance documentation explicitly requires `IET_RELEASE_PROFILE=full`; `office_alpha` remains only a historical/focused experience profile.

## Release boundary

AI Copilot is explicitly deferred. Generic Workflow extraction, Reputation and Recommendations are also not publication blockers for v1. Existing deterministic Actions and durable domain evidence remain authoritative.

## Validation

Pre-integration release-gate candidate `38f95175040234593bc927f895954c893a38e9fd` passed GitHub Actions CI run `36143475001`:

- 543 tests / 3316 assertions;
- changed-file Pint clean against the active integration baseline;
- PHPStan clean;
- npm install/audit and Vite production build green;
- migration rollback/reapply, scheduler, Reverb command and database-queue smoke green;
- SQLite backup/restore smoke green;
- Composer security audit clean.

The subsequent canonical-doc evidence sync is documentation-only and must itself pass exact-head CI before the integration PR is merged. The immutable integrated release-candidate SHA is frozen only after `integration/ideal-v1` is green.

### RC local-bootstrap correction

The first frozen RC exposed a documentation-order defect during owner-local acceptance: the worksheet instructed `php artisan optimize:clear` before restoring the RC's Composer dependencies. With `BROADCAST_CONNECTION=reverb`, a stale pre-RC `vendor/` could therefore fail while resolving `Pusher\\Pusher`, even though `composer.lock` correctly contains `pusher/pusher-php-server` 7.3.0 and CI installs it successfully.

Correction: local acceptance now runs `composer install --no-interaction --prefer-dist` before any Artisan command and verifies the Pusher package before `optimize:clear`. This is a release-handoff/bootstrap correction, not a domain-code change.


## Human acceptance

Browser acceptance is intentionally not fabricated remotely. The final candidate is handed to the owner with `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`; browser findings become regression-tested correction commits before tagging the stable release.
