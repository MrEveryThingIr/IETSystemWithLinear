# IET Local Acceptance Worksheet

## Purpose

This worksheet is intentionally cumulative. Remote development may continue without local browser interruption, but every checkpoint must remain reproducible later.

**Never run `migrate:fresh` against the owner's continuing database. Back up first.**

## Common preparation

For every checkpoint:

~~~bash
git status --short
git fetch origin
~~~

If local tracked changes exist, preserve them before switching. Do not reset/discard them casually.

Environment for the focused first release experience:

~~~text
APP_NAME=IET
IET_RELEASE_PROFILE=office_alpha
~~~

After environment changes:

~~~bash
php artisan optimize:clear
~~~

---

## Checkpoint F0 — Clean pre-AI office foundation

Remote branch:

~~~text
integration/ideal-v1
~~~

Remote runtime checkpoint:

~~~text
SHA: f5b55fb3f1d426e995549efc90856cfd9ac60b34
CI: 35924838454
Result: 411 tests / 2257 assertions; Pint 366 files; PHPStan/Vite/migrations/ops/backup/npm/Composer green
~~~

### Local sync

~~~bash
git fetch origin
git switch integration/ideal-v1
git pull --ff-only origin integration/ideal-v1
git status --short
git rev-parse HEAD
# the F0 runtime checkpoint is:
# f5b55fb3f1d426e995549efc90856cfd9ac60b34

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/AccessInvitationJourneyTest.php \
  tests/Feature/IntentDirectoryReleaseTest.php \
  tests/Feature/ReleaseExperienceTest.php \
  tests/Feature/InvitationJourneyTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
~~~

### Browser story

Use `docs/EXAMPLE_STORY_WORLD.md`.

- [ ] Diego/admin can create a standalone Access Invitation.
- [ ] Alice can inspect the welcome page before registration.
- [ ] Alice registers without joining a Group.
- [ ] verification returns Alice to Get Started.
- [ ] Alice records Property / Construction Service / Capital Needs or Offers.
- [ ] cash/mixed-value preference is visibly non-binding.
- [ ] Bob, as a second verified user, sees only intents allowed by visibility.
- [ ] private Profile identity does not leak merely because an intent is shared.
- [ ] Diego can invite already registered Bob to a Group.
- [ ] unknown email is rejected by the Group-invitation creation flow.
- [ ] mobile + RTL smoke.

---

## Future checkpoint template

Each remote milestone appends a concrete section in this format:

~~~text
## Checkpoint <id> — <name>

Integration SHA:
CI run:
Migrations:
Remote report:

### Local sync
<exact commands>

### Focused tests
<exact commands>

### Browser story
[ ] exact user-visible action
[ ] expected durable result
[ ] visibility/authorization expectation
[ ] negative guarantee

### Continuity
What previous Alice/Bob/Carol/Diego objects should still exist and be reused.
~~~

The final release section will include the entire 0→100 path in one chronological browser script.
