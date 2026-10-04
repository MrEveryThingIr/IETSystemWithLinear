# Formal v1 production deployment

This branch is the production-release preparation line for the first formal
Everything deployment. The authoritative application source remains testable,
but the host should receive the generated runtime ZIP rather than a normal Git
clone.

## Why a package instead of cloning the repository

A normal clone transfers Git history and development-only material but still
does not contain two required runtime outputs: `vendor/` and
`public/build/`. The production-package workflow creates both and then ships
only runtime files. It deliberately excludes tests, docs, AI-agent rules,
development reports, Node modules, frontend source tooling and the Git history.

## 1. Host requirements

Use PHP 8.4 when available (the release is CI-certified on PHP 8.4) with at
least: mbstring, DOM/XML, fileinfo, cURL, Intl, OpenSSL and PDO MySQL. MySQL 8
is the certified database family.

The host must provide a safe way to run PHP CLI commands during deployment
(SSH, a control-panel terminal, or an equivalent non-public command runner).
Never expose a web route that runs migrations or Artisan commands.

The domain document root must point to the Laravel `public/` directory. Do
not point the web server at the project root because that risks exposing
`.env`, source and storage files.

## 2. Build the runtime ZIP

From GitHub Actions choose **Build production package**, run it from
`release/formal-v1-production`, and use version `v1.0.0`.

Download the resulting artifact and verify the SHA-256 checksum before upload.
The ZIP already contains production Composer dependencies and built Vite
assets; Node and Composer are therefore not required on the host for ordinary
deployment.

## 3. Upload layout

A recommended layout is:

```
/home/account/apps/everything/current/   <- extracted package
/home/account/public_html/               <- domain document root mapped to current/public
```

If the hosting panel supports custom document roots, point the domain directly
to `/home/account/apps/everything/current/public`.

Keep releases outside the public web root whenever possible.

## 4. Production environment

Copy `production.env.example` to `.env` and edit the copy.

Do not copy the local development `.env` wholesale. Production must at least
set:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-real-domain`
- one permanent `APP_KEY`
- MySQL host/database/user/password
- `SESSION_SECURE_COOKIE=true` after HTTPS is working
- a real SMTP mail transport
- `IET_RELEASE_PROFILE=full`
- `IET_SEED_DEMO_DATA=false`

For ordinary shared hosting use `QUEUE_CONNECTION=sync` initially. This keeps
notifications from becoming stuck when no persistent worker exists.

Do not regenerate `APP_KEY` on later deploys. It must survive upgrades and
rollbacks.

## 5. First boot

From the extracted release directory:

```bash
php artisan optimize:clear
php artisan key:generate --force
php artisan migrate --force
php artisan iet:bootstrap-superadmin --username=MrEveryThing --email=YOUR_REAL_EMAIL
php artisan storage:link
php artisan optimize
```

Run `key:generate` only on the first deployment. On every later deployment,
reuse the existing `.env` and existing APP_KEY.

The superadmin command is intentionally one-time and idempotent: once an active
platform superadmin exists it makes no change. If the bootstrap password is not
present in the environment, the command asks for it interactively without
placing it in shell history. Remove any temporary
`IET_BOOTSTRAP_SUPERADMIN_PASSWORD` value from `.env` immediately after
bootstrap.

Do **not** run `migrate:fresh` or production seeders on a live database.

## 6. Writable directories

The PHP/FPM user must be able to write:

```
storage/
bootstrap/cache/
```

Do not make the whole application world-writable. Prefer the hosting account
owner/web-server group and the minimum permissions supported by the provider.

## 7. Scheduler

The product has minute-level scheduled work for agreements, due contracts,
notifications and reminders. Add exactly one cron entry:

```cron
* * * * * cd /home/account/apps/everything/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Adjust the PHP binary and path to the host.

With `QUEUE_CONNECTION=sync`, no queue worker is required for the first
release.

If you later switch to `QUEUE_CONNECTION=database`, run a persistent worker
or a frequent host job that consumes both queues:

```bash
php artisan queue:work database --queue=notifications,default --tries=3 --timeout=60
```

On shared hosting without Supervisor, a once-per-minute cron can use
`--stop-when-empty`.

## 8. Realtime / Reverb

The first shared-host release should keep:

```
BROADCAST_CONNECTION=log
```

unless the provider explicitly supports a continuously running Reverb/WebSocket
process and the required public port/proxy configuration. Realtime delivery can
be enabled later without blocking normal page operation.

## 9. Mail is not optional for formal publication

The application uses verified accounts. `MAIL_MAILER=log` is a development
setting and does not deliver verification/invitation mail. Configure the host's
SMTP server or another supported transactional mail service before inviting
real users.

## 10. Smoke test after deployment

Run:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan config:show app
```

Then verify in the browser:

1. `https://your-domain/up` is healthy.
2. HTTPS stays enabled and login works.
3. Superadmin login works.
4. The public landing page renders its Vite assets.
5. A standalone public Business page `/b/{slug}` works in an incognito window.
6. Public Real Estate intake submits successfully.
7. Promote a case to a catalog draft, preview it, publish it, and verify it
   appears publicly.
8. Upload/view a Business introduction video.
9. Send a real verification/invitation email.
10. Confirm the scheduler is executing in the hosting control panel/logs.

Only after this smoke test should the release be tagged `v1.0.0`.

## 11. Upgrades and rollback

Before every later deployment:

1. back up the MySQL database and uploaded/private storage;
2. keep the previous release directory;
3. upload/extract the new package into a new directory;
4. copy/reuse the existing production `.env`;
5. run `php artisan optimize:clear`;
6. run `php artisan migrate --force`;
7. run `php artisan optimize`;
8. switch the document-root/symlink to the new release;
9. smoke-test `/up` and login.

Application rollback is then a directory/symlink switch. Database rollback is
not automatically safe after schema/data migrations, which is why a database
backup is mandatory before upgrading.
