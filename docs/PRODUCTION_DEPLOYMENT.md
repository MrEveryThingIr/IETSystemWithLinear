# Formal v1 production deployment

This document is the canonical procedure for the formal production line
(`release/formal-v1-production`) on the current host:

- Netafraz shared hosting / DirectAdmin, domain `https://imeverything.ir`
- no SSH, no Composer, no npm/Node on the host
- hosting support runs exact PHP/Artisan commands on request
- PHP 8.4 for web and CLI; CLI binary: `/usr/local/php-8.4/bin/php`
- `proc_open()` disabled, no Supervisor / persistent processes
- MySQL/MariaDB server with a MyISAM default engine and legacy TIMESTAMP defaults

**Rule: never patch PHP source on the host.** Every compatibility fix lives in
GitHub, and the host only ever receives a ZIP built by the *Build production
package* GitHub Action. If something fails on the host, fix it in GitHub and
build a new package.

All commands below use `$PHP` for `/usr/local/php-8.4/bin/php` and are run
from inside the release directory (for example
`/home/imeveryt/domains/imeverything.ir/app/releases/1.0.1`).

## Release notes

- **v1.1.0** — classroom-ready groups: join links, inviting people by name,
  a simpler group home with chat, editable chat messages, per-role rules on
  content and answer endorsements. Three additive migrations; upgrade an
  existing v1.0.1 database with the ordinary upgrade in section 5c.
- **v1.0.1** — shared-host compatibility (InnoDB, DATETIME domain times,
  scanner-less uploads, in-process scheduler, release manifest).

## 1. Shared-host compatibility built into the code

| Host limitation | What the application does |
| --- | --- |
| Default engine MyISAM ("Specified key was too long; max key length is 1000 bytes") | MySQL/MariaDB connections create every table with `DB_ENGINE` (default `InnoDB`). |
| Legacy TIMESTAMP defaults ("Invalid default value for 'proposed_at'") | Application/domain times are `DATETIME` columns with no implicit defaults. Only Laravel's nullable `timestamps()` and explicit `useCurrent()` bookkeeping columns stay `TIMESTAMP`. CI runs a fresh install on MariaDB 10.6 with `default-storage-engine=MyISAM` and `explicit_defaults_for_timestamp=0`. |
| No `proc_open` → media uploads returned HTTP 500 | With `MEDIA_SCANNER_ENABLED=false` no process is ever started. Uploads are stored privately, their SHA-256 is verified, and they are recorded as `scan_status=unavailable` (never `clean`). With `MEDIA_SCANNER_REQUIRED=false` they become usable; with `true` they stay unusable. Scanner failures on a `sync` queue are recorded on the Asset and never turn the upload into a 500. |
| No `proc_open` → scheduled commands could not start | `schedule:run` detects that child processes are unavailable and runs the scheduled Artisan commands inside its own process (`IET_SCHEDULE_IN_PROCESS=auto`). |
| No queue worker | `QUEUE_CONNECTION=sync`. |
| No WebSocket process | `BROADCAST_CONNECTION=log`. |
| Cache table missing on first install | First install clears caches with `CACHE_STORE=array` (section 6). |

## 2. Build the runtime package (GitHub)

1. Every push to `release/formal-v1-production` runs **Build production
   package** automatically. The package version comes from `APP_VERSION` in
   `deploy/production.env.example` (currently `1.1.0`, so the package is
   `iet-v1.1.0`). A manual run with an explicit version is also possible.
2. Open that run for the exact commit you want to deploy.
3. Download the artifact `iet-v1.1.0`. It contains:
   - `iet-v1.1.0.zip` — the runtime package (root directory `iet-v1.1.0/`)
   - `iet-v1.1.0.zip.sha256` — checksum of the ZIP
   - `iet-v1.1.0.release-manifest.json` — copy of the package's manifest
4. Verify the checksum before uploading: `sha256sum -c iet-v1.1.0.zip.sha256`.

The package contains `vendor/` (production dependencies only),
`public/build/manifest.json`, `artisan`, the application code, migrations,
config, `production.env.example` and `release-manifest.json`. Composer and
npm are not needed on the host.

### Release manifest

`release-manifest.json` sits in the package root and contains no secrets:

```json
{
  "schema": 1,
  "package": "iet-v1.0.1",
  "package_version": "v1.0.1",
  "application_version": "1.0.1",
  "source": {
    "repository": "MrEveryThingIr/IETSystemWithLinear",
    "git_commit": "<40-character commit SHA>",
    "git_ref": "refs/heads/release/formal-v1-production",
    "git_ref_name": "release/formal-v1-production"
  },
  "build": {
    "built_at": "<UTC ISO-8601>",
    "workflow_run_id": "<GitHub Actions run id>",
    "workflow_run_attempt": "1",
    "workflow_run_url": "https://github.com/.../actions/runs/<id>",
    "php_target": "8.4",
    "php_build_version": "8.4.x",
    "node_build_version": "v22.x",
    "composer_dev_dependencies": false
  },
  "integrity": {
    "composer_lock_sha256": "...",
    "package_lock_sha256": "...",
    "vite_manifest_sha256": "..."
  }
}
```

On the host, `$PHP artisan about` shows a **Release** section (package,
commit, ref, build time, workflow run) and a **Media scanner** section. Compare
the commit with the GitHub branch HEAD and the workflow run that produced the
artifact.

## 3. Directory layout

```
/home/imeveryt/domains/imeverything.ir/app/
├── releases/
│   ├── 1.0.0/
│   └── 1.0.1/              <- extracted iet-v1.0.1/ contents
├── shared/
│   ├── .env                <- the ONE production environment file
│   └── storage/            <- persistent: private media, logs, sessions, cache
└── current -> releases/1.0.1
```

- **`.env` is copied**, not linked: this host does not allow symlinks to
  hidden files. Copy `shared/.env` into every new release directory. Edit only
  `shared/.env`, then copy it again into the active release.
- **`storage` is a symlink**: in each release, delete the packaged `storage/`
  directory and create `storage -> ../../shared/storage`. All private media
  (profile images, content/context assets, planner evidence, submission files,
  business listing media and intro videos, public real-estate images/audio/video)
  is written to `storage/app/private` on the `local` disk, so it survives
  release switches. Nothing uses the `public` disk; `storage:link` is not needed.
- The domain document root points to `current/public`.
- `storage/` and `bootstrap/cache/` must be writable by the PHP user.

## 4. Production `.env`

Start from `production.env.example`. The shared-host baseline is:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://imeverything.ir
APP_VERSION=1.0.1
IET_RELEASE_PROFILE=full
IET_SEED_DEMO_DATA=false

DB_CONNECTION=mysql
DB_ENGINE=InnoDB

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

CACHE_STORE=database

QUEUE_CONNECTION=sync
QUEUE_AFTER_COMMIT=true

BROADCAST_CONNECTION=log

FILESYSTEM_DISK=local

MEDIA_SCANNER_ENABLED=false
MEDIA_SCANNER_REQUIRED=false

MAIL_MAILER=smtp
```

plus the real database credentials, SMTP credentials and the permanent
`APP_KEY`. Never commit the real `.env`.

### APP_KEY is permanent

- `APP_KEY` is generated **once**, on the very first installation, and is
  already set in the existing production `shared/.env`.
- **Never run `key:generate` again** — not for upgrades, not for rollbacks,
  not for a new release directory. A new key logs every user out and makes
  encrypted data unreadable.
- Every release receives a copy of the same `shared/.env`, therefore the same
  key.

### Upload limits

Application limits (kilobytes, `config/media.php`): profile images 8192,
general assets 12288, business intro video 51200, real-estate images 12288,
audio 25600, video 102400. Each can be lowered with
`MEDIA_LIMIT_*_KB`. Validation always uses the lower of the application limit
and the host's `upload_max_filesize` / `post_max_size`, so a host with smaller
limits shows a normal validation message instead of an error page.

## 5. Commands to send to hosting support

Always give support the exact commands with the full PHP binary and the full
release path.

### 5a. First installation (empty database only)

```bash
cd /home/imeveryt/domains/imeverything.ir/app/releases/1.0.1
CACHE_STORE=array /usr/local/php-8.4/bin/php artisan optimize:clear
/usr/local/php-8.4/bin/php artisan migrate --force
/usr/local/php-8.4/bin/php artisan iet:bootstrap-superadmin --username=MrEveryThing --email=YOUR_REAL_EMAIL
/usr/local/php-8.4/bin/php artisan optimize
```

> **The first command MUST be prefixed with `CACHE_STORE=array`.**
> `CACHE_STORE=database` reads the `cache` table, which does not exist until
> `migrate` has run, so a plain `optimize:clear` fails on an empty database.
> After migrations have run, `CACHE_STORE=database` (as set in `.env`) is
> correct, and plain `optimize:clear` works.

`key:generate` is **not** in this list because the production `APP_KEY`
already exists. Only a brand-new site with an empty `APP_KEY` would run
`key:generate --force` once, before anything else.

### 5b. Re-installing the pre-production database for v1.0.1

The first v1.0.0 attempt ran migrations that were hand-edited on the host.
Because the site still has **no real production data**, the clean way to make
the database match GitHub exactly is to rebuild it once with the v1.0.1
migrations. This **deletes all tables and data**; take a database backup first
and only do it while there is no real data:

```bash
cd /home/imeveryt/domains/imeverything.ir/app/releases/1.0.1
CACHE_STORE=array /usr/local/php-8.4/bin/php artisan optimize:clear
CACHE_STORE=array /usr/local/php-8.4/bin/php artisan migrate:fresh --force
/usr/local/php-8.4/bin/php artisan iet:bootstrap-superadmin --username=MrEveryThing --email=YOUR_REAL_EMAIL
/usr/local/php-8.4/bin/php artisan optimize
```

Never run `migrate:fresh` once real users or data exist.

### 5c. Ordinary upgrade (existing database, real data)

1. Back up the database and `shared/storage`.
2. Extract the new package into a new `releases/<version>/` directory.
3. Copy `shared/.env` into it; replace its `storage/` with the
   `../../shared/storage` symlink.
4. Run:

   ```bash
   cd /home/imeveryt/domains/imeverything.ir/app/releases/<version>
   /usr/local/php-8.4/bin/php artisan optimize:clear
   /usr/local/php-8.4/bin/php artisan migrate --force
   /usr/local/php-8.4/bin/php artisan optimize
   ```

5. Switch `current` to the new release.
6. Run `/usr/local/php-8.4/bin/php artisan schedule:interrupt`.
7. Smoke-test (section 7).

### 5d. Rollback

Switch `current` back to the previous release directory and run
`optimize:clear` + `optimize` there. That is an application rollback only:
if the newer release ran migrations, restore the database backup taken in
step 1 of the upgrade.

## 6. Scheduler

One cron entry in DirectAdmin:

```cron
* * * * * cd /home/imeveryt/domains/imeverything.ir/app/current && /usr/local/php-8.4/bin/php artisan schedule:run >> /dev/null 2>&1
```

Without `proc_open`, `schedule:run` runs the scheduled Artisan commands in its
own process (`IET_SCHEDULE_IN_PROCESS=auto`, the default). No queue worker is
required with `QUEUE_CONNECTION=sync`.

## 7. Smoke test after activation

```bash
/usr/local/php-8.4/bin/php artisan about
/usr/local/php-8.4/bin/php artisan migrate:status
/usr/local/php-8.4/bin/php artisan schedule:list
```

In `about`, confirm the Release commit matches GitHub and that Media scanner
shows `Enabled: no`, `Required: no`. Then in the browser:

1. `https://imeverything.ir/up` is healthy; HTTPS and login work.
2. Superadmin login works.
3. Upload a profile image and see it displayed (no HTTP 500).
4. Upload a file to content, planner evidence and a business listing.
5. Public Real Estate intake submits with images.
6. A public Business page `/b/{slug}` works in a private window.
7. A real verification/invitation email arrives.
8. The scheduler runs (check `storage/logs`).

Only after this real-host browser acceptance should the release be tagged
`v1.0.1`.
