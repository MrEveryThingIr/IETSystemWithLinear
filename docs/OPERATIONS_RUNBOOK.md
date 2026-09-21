# IET Operations Runbook

## Purpose

This is the executable provider-neutral operating baseline for Phase 2. It describes how to build, deploy, run, diagnose, back up, restore, and update the current Laravel application without relying on developer memory.

It does not select a paid hosting, email, object-storage, or monitoring provider. Provider selection with meaningful cost, privacy, or compliance implications requires owner approval.

## Supported baseline

- PHP: 8.4 for CI/production baseline; `composer.json` currently allows `^8.3`.
- Composer: 2.x.
- Node: 22.12+.
- Laravel: 13.x as locked by Composer.
- Queue: database by default; asynchronous queue connections dispatch after open database transactions commit unless explicitly overridden.
- Scheduler: Laravel scheduler, one system trigger invoking `schedule:run` every minute.
- Local mail: `log`.
- Local/private files: Laravel `local` disk rooted at `storage/app/private`.
- Production database/storage/mail target: must be selected and recorded before Phase 2 closure.

## Environment and secrets

Never commit a real `.env`, application key, database password, mail credential, cloud credential, webhook secret, or monitoring token.

Required production values include at minimum:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`
- `APP_URL`
- `APP_VERSION` set to the deployed Git SHA or release tag
- database connection/credentials
- session/cache/queue configuration
- mail transport/from identity for enabled transactional mail
- private filesystem/object-storage credentials when not using local private storage

Production logging should normally use `daily` files on a single host or `stderr` under a platform/container that centralizes logs. Use `LOG_LEVEL=info` or stricter unless a temporary diagnostic window is approved.

## Build and release sequence

A release should be built from an immutable commit.

1. Confirm CI is green for the exact commit.
2. Record the commit SHA as `APP_VERSION`.
3. Back up the production database before migrations.
4. Enter maintenance mode if the migration/release requires it.
5. Install PHP dependencies with:
   `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
6. Install/build frontend dependencies from a committed npm lockfile once Phase 2 adds it:
   `npm ci && npm run build`.
7. Run `php artisan migrate --force`.
8. Run `php artisan optimize`.
9. Restart long-lived workers with `php artisan queue:restart`.
10. Exit maintenance mode.
11. Verify `/up`, login, and the current invitation/onboarding smoke path.
12. Confirm logs show the expected `app_version` and requests return `X-Request-Id`.

Do not run destructive schema resets in production.

## Rollback

Application rollback and database rollback are separate decisions.

- Application code may be rolled back to a previously known-good artifact/commit.
- Do not blindly run `migrate:rollback` after a failed release. A down migration may be destructive or incompatible with data written after deploy.
- If a schema/data change prevents safe code rollback, restore from the pre-release backup into a controlled recovery path.
- Record the incident, deployed SHA, rollback SHA, database state, and verification performed.

## Queue operating model

The current default is the database queue.

Example worker command:

~~~text
php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600
~~~

Use a process supervisor (systemd, Supervisor, container platform, or equivalent). Never depend on an interactive terminal remaining open.

After every deployment run:

~~~text
php artisan queue:restart
~~~

Failed-job operations:

~~~text
php artisan queue:failed
php artisan queue:retry <uuid>
php artisan queue:retry all
php artisan queue:forget <uuid>
~~~

Retry only after understanding whether the job is idempotent and whether its side effect already occurred.

The scheduler prunes failed jobs and batches older than seven days. Retention may be raised for production incident requirements, but do not disable failed-job visibility merely to hide failures.

## Scheduler operating model

The application currently schedules:

- due Group Agreement activation every minute;
- failed-job pruning daily;
- queue-batch pruning daily.

Production needs exactly one scheduler trigger per deployment unit:

~~~text
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
~~~

Or use the hosting platform's equivalent scheduler service.

Verify with:

~~~text
php artisan schedule:list
php artisan schedule:run
~~~

For multi-host deployments, prevent duplicate scheduler execution at the infrastructure level or move to a shared-cache/on-one-server design only after that topology is actually selected.

## Transactional email

Phase 1 intentionally uses manual private invitation-link delivery. Do not change that product behavior implicitly.

Existing verification/password-reset mail uses Laravel's configured mail transport.

- local/development: `MAIL_MAILER=log` is acceptable;
- production: configure an approved SMTP/API transport entirely through secrets/environment;
- use a verified sender identity/domain;
- keep `APP_URL` correct so signed/generated links are correct;
- never include private Admission details or raw secrets beyond the minimum URL/token required by the mail flow;
- inspect application logs and failed jobs for delivery failures when queued mail is introduced.

Choosing SES/Postmark/Resend/Mailgun or another provider is an owner decision. Phase 2 may configure an approved provider later without changing onboarding semantics.

## Health, logs, and diagnostics

Laravel liveness is available at `/up`.

Web requests receive an `X-Request-Id` response header, and request logs share that correlation ID. All application log channels share `app_version`, sourced from `APP_VERSION`.

Minimum incident evidence:

- timestamp/timezone;
- `APP_VERSION`;
- request ID where available;
- route/action;
- authenticated User/Actor IDs only where already safe and appropriate;
- exception class/message/stack trace in server-side logs;
- queue/job UUID and attempts for job failures;
- deployment timestamp and migration set.

Do not log invitation plaintext tokens, passwords, reset tokens, mail credentials, storage credentials, or private uploaded content.

A third-party monitoring/error product is optional and requires approval before adding credentials or sending private telemetry externally.

## Database backup and restore

A production backup policy is incomplete until restore is exercised.

Required policy before Phase 2 closure:

- automated backups at a frequency compatible with acceptable data loss;
- encrypted storage where backups leave the database host;
- access restricted to operational administrators;
- retention documented;
- at least one restore drill into an isolated database;
- application verification against the restored copy;
- drill date/result recorded in the Phase 2 report.

### SQLite/local drill

For local SQLite only, stop writers and copy the database file or use SQLite's backup command. Restore into a separate file, point a temporary environment at it, then run read-only verification and application tests.

### MySQL/MariaDB production pattern

Use a transactionally consistent dump tool appropriate to the selected server version. Restore into a new database/schema first; never overwrite the only production copy during a drill. Verify migration table state, row counts for critical tables, authentication/onboarding read paths, and application health.

### PostgreSQL production pattern

Use `pg_dump`/custom format and restore into an isolated database. Verify the same application-level invariants before considering the drill successful.

The exact production command and target are intentionally not frozen until the deployment database is selected.

CI performs a provider-neutral SQLite backup→restore smoke against its freshly migrated test database and verifies restored migration history. This is recovery-mechanics evidence only; it does **not** satisfy the required production-target restore drill.

## Private storage and media

Existing Assets are private and must remain private.

- local disk root is `storage/app/private`;
- production object storage may use S3-compatible private buckets once approved;
- never switch private Assets to the public disk for convenience;
- downloads/streams remain application-authorized;
- object/storage backups must match database retention closely enough that restored database references do not point permanently at missing objects;
- MIME/size/scan/processing failures must fail closed for publication-sensitive media;
- cloud credentials remain outside source control.

## Abuse-control inventory

Current controls:

- login: per normalized email + IP, five attempts/minute;
- invitation registration: per IP, five attempts/minute;
- verification resend: per authenticated User, one/minute;
- verification-link route: six/minute plus signed URL;
- password reset request: per IP, five/minute;
- password reset execution: per IP, five/minute;
- invitation acceptance: dedicated per-identity named limiter, ten/minute.

Do not merge these actions into one shared throttle bucket. Review new sensitive mutations individually.

## Dependency and security updates

See `docs/SECURITY_UPDATE_PROCESS.md`.

CI performs Composer metadata validation, formatting, static analysis, migration/scheduler/queue smoke, a SQLite restore smoke, tests, frontend build, Composer advisory audit, and an npm high-severity advisory gate. Dependabot monitors Composer, npm, and GitHub Actions manifests.

`package-lock.json` is committed and CI installs JavaScript dependencies exclusively with `npm ci`.

## Release verification checklist

Before calling a release healthy:

- exact commit CI green, including strict Composer/npm lockfile installs;
- environment/secrets present;
- backup completed;
- migrations successful;
- workers restarted and consuming jobs;
- scheduler exercised;
- `/up` succeeds;
- no new failed jobs;
- expected `APP_VERSION` appears in logs;
- a web response has `X-Request-Id`;
- enabled transactional mail path exercised;
- private Asset retrieval remains authorized;
- Phase 1 invitation/onboarding smoke path still works.

## Phase 2 closure evidence still requiring infrastructure

Repository implementation alone cannot prove:

- the selected production deployment target;
- worker supervisor behavior on that target;
- scheduler supervision on that target;
- external transactional-mail delivery;
- remote log/monitoring behavior;
- automated production backup retention;
- a real restore drill.

Record those results in the Phase 2 report only after they actually happen.
