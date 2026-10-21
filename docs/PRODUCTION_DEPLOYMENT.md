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

- **v1.8.0** — shared categories and capabilities. **One additive migration**
  (`concept_kinds`, `concept_suggestions`, `capabilities`,
  `professions.concept_id`, two concept relation types). Ordinary upgrade
  (section 5c) with two extra commands for hosting support:
  1. **Before** `migrate`, inside the new release directory (after copying
     `.env` and linking `storage`, still on the old database):
     `/usr/local/php-8.4/bin/php artisan iet:inventory > ~/iet-inventory-before.txt`
     (read-only; send the file to the product owner — it lists every existing
     profession, person and business-member skill and where each will map).
  2. **After** `migrate` and `optimize`:
     `/usr/local/php-8.4/bin/php artisan iet:taxonomy:sync`
     installs the shared category tree (16 areas, ~180 subcategories, four
     languages) and copies the old skills into capabilities. Safe to repeat;
     `--dry-run` only reports. The old tables are never changed. If the command
     is forgotten, the same work runs the first time someone opens a category
     picker.
  3. `iet:inventory` again after the sync; the counts of old skill rows must be
     unchanged and `skills.capabilities_already_created` equal to them.
  - Reviewers of category suggestions are superadmins (and roles with the
    *manage concepts* permission): *Administration → Category suggestions*.
- **v1.7.1** — every screen size, and speed. **No migration**; ordinary
  upgrade (section 5c); `migrate` reports nothing to run.
  - Pages can no longer become wider than a phone (which made phones zoom out
    and show the hidden side menu). All pages were checked at 320–1920px in
    Persian, English, Arabic and Chinese. Profile pictures keep their shape.
  - Fonts (Inter, Vazirmatn) are now part of the build in `public/build/assets`
    instead of being loaded from Google Fonts.
  - `public/.htaccess` now compresses text responses and caches the hashed
    files in `/build/assets` for a year (`mod_deflate` / `mod_headers`; both
    are skipped if the server does not have them). `sw.js` is never cached.
  - Fewer database queries: Today loads first and recent activity right after;
    the content list, the content index and the studio need far fewer checks.
  - Ask hosting support to confirm OPcache is on for PHP 8.4
    (`/usr/local/php-8.4/bin/php -i | grep opcache.enable` for CLI, and the
    web PHP's `opcache.enable => On`); it makes every page several times faster.
- **v1.7.0** — reminders that are never forgotten, device notifications and
  speed. **One additive migration** (`plan_schedule_rules.month_day` and
  `.calendar`, `notification_outbox.pushed_at`, new table
  `push_subscriptions`); ordinary upgrade (section 5c).
  - Plan type *Due date* and monthly repeats on the same day of the user's
    calendar (Persian months included). *Important* plans are reminded up to a
    week before, also **by email**, and stay on Today under *Don't forget*
    until marked done.
  - **Device notifications** (Web Push, no extra package): the scheduler's
    `notifications:push-devices` command (every minute, already part of
    `schedule:run`) sends reminders to subscribed phones/computers, so the
    cron entry in section 6 must be active. The signing keys (VAPID) are
    created on first use in `storage/app/private/webpush-vapid.json` — in the
    shared storage, so they survive releases; do not delete that file (all
    devices would have to turn notifications on again). Optional fixed keys:
    `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`.
  - Push delivery needs HTTPS and outbound HTTPS from the host to the
    browsers' push services (Google FCM for Chrome/Edge on Android and
    Windows, Mozilla, Apple). If the host cannot reach them the test button
    says so; email and Today still cover important reminders. On iPhone the
    site must be added to the Home Screen first.
  - The plan wizard shows the reason when a plan cannot be saved instead of a
    generic error, and returns to the step with the problem.
  - Speed: authorisation lookups are memoised per request, sidebar links
    navigate without a full reload, dialogs close immediately, and the
    content list loads 24 items at a time.
- **v1.6.0** — the Planner redesign. **One additive migration**
  (`planner_labels`, `meeting_requests`, `meeting_invitees`, `meeting_slots`,
  `meeting_votes`); ordinary upgrade (section 5c).
  - New plans are made with a short wizard (what → when → who → details →
    review) and a visual time picker: today / tomorrow / this or next week /
    this or next month and its weeks → a day → drag on the day's timeline.
    The exact date-and-time fields stay one tap away.
  - Plan types (event, task, routine, meeting, focus time, reminder) with
    default colours; personal labels with colours (Planner → *Labels*), and a
    type/label/colour editor on every plan page.
  - **Meetings**: offer several times to users and/or guests (private links at
    `/meet/{token}`, no account needed). Invitees answer yes/maybe/no or
    suggest a time; with one invitee a single "yes" agrees it, otherwise the
    organizer chooses. The agreed time becomes a plan in each user's planner
    (in their own timezone); guests get an `.ics` file. A guest who signs in
    and opens the link, or who later verifies an account with the invited
    email, gets the meeting in their planner.
  - Repeating plans are listed once with their next times; a plan page shows
    the next few occurrences in full and the rest in short lists.
- **v1.5.0** — content improvements and the version label. **No database
  migrations**; ordinary upgrade (section 5c), the backup is still advised.
  - Authors can **delete content permanently** (warning + type the title) as
    long as nobody else has engaged with it; otherwise only archive. Content
    can be **renamed** in one step (published content shows the new name
    immediately).
  - **Section types and tags** (question, exercise, discussion, definition,
    key point, example, summary) map to reader actions; question/exercise
    sections can be answered directly and answers marked correct. Stored in
    the block data, so older content is unaffected. See
    `app/Support/Content/SectionSemantics.php`.
  - Reader menus, the note composer and dialogs stay on screen (bottom
    sheets on phones).
  - The running version is shown next to the site name and opens
    `/whats-new`. It is read from the package's `release-manifest.json`, so
    `APP_VERSION` in `shared/.env` no longer has to be edited for it.
- **v1.4.0** — **Bookkeeping replaces the old financial system**, plus
  **faster video over slow or VPN connections**.
  - The old Accounting, Financial Obligations/Settlements, money intentions and
    IET exchange/valuation screens, code and **tables are removed**
    (irreversible). Their data is not carried over: **take a full database
    backup before upgrading** and keep it; it is the only copy of that old data.
    IET exchange will come back later as its own, carefully designed feature.
  - New **Bookkeeping** (`/bookkeeping`, the former *Money* facility, so
    existing access grants carry over): a private personal book (wallets,
    categories, income/spending/moves, month totals in the user's calendar,
    receipts, undo) and shared/project books where each debt or payment waits
    for the other side's confirmation, with proof files, disputes,
    corrections, an append-only history, a book chat and Today/notification
    reminders. Everything is in toman (whole numbers).
  - **Video**: uploaded MP4/MOV files are rewritten so playback can start
    immediately (index moved to the front, pure PHP, no FFmpeg), media files
    are cached by the browser for a day with ETag/304 revalidation, and a
    command prepares playback copies of videos uploaded earlier (run it once,
    section 5c step 4; the scheduler then continues 20 a night).
  - Two migrations: one removes the legacy finance tables, one creates the
    `bk_*` bookkeeping tables. Ordinary upgrade (section 5c), but the backup
    in step 1 is mandatory this time.
- **v1.3.0** — everything in v1.2.0 below, plus **sharing outside the
  system**: the author of published Content can make it *public* (anyone can
  read it without an account; it is listed at `/public` and previewed on the
  home page) or send **personal invitation links** (`/read/…`) that let one
  person read one piece — a course link also opens its lessons. Links can
  expire, can be turned off at any time, and show how often they were opened.
  Guests only ever see the published version: no drafts, history, comments or
  places. In a group or business only an author who also manages that place's
  content can share outside. Set `PUBLIC_CONTENT_INDEX=false` to keep the site
  invitation-only (no `/public` list or home preview; direct public links and
  invitation links keep working). One more additive migration; v1.2.0 was not
  deployed, so an existing v1.1.0 database upgrades straight to v1.3.0 with
  the ordinary upgrade in section 5c (three migrations in total).
- **v1.2.0** — everyday usability and lesson video:
  Planner evidence opens as a post (image, video, audio, PDF, file) through
  authorized routes; Today stays short however busy the day is; dates show no
  repeated timezone and the Gregorian date appears on hover/tap (profile
  setting: hidden / hover / always); one Content page with plain *Create*
  (Note, Article, Lesson, Course, Evidence); a single Content Studio (write,
  insert media, AI / appearance / chapters as panels, history in a drawer);
  lesson video and long audio upload **in parts** (not limited by
  `post_max_size`, resumes after a refresh), with poster frame, captions
  (WebVTT/SRT), playback speed and seeking; Course → Module → Lesson with
  previous/next. Two additive migrations; upgrade an existing v1.1.0 database
  with the ordinary upgrade in section 5c. Not included: adaptive (HLS)
  streaming, which needs an FFmpeg worker or a video service — the
  `VideoProcessor` interface is ready for one.
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
   `deploy/production.env.example` (currently `1.8.0`, so the package is
   `iet-v1.8.0`). A manual run with an explicit version is also possible.
2. Open that run for the exact commit you want to deploy.
3. Download the artifact `iet-v1.8.0`. It contains:
   - `iet-v1.8.0.zip` — the runtime package (root directory `iet-v1.8.0/`)
   - `iet-v1.8.0.zip.sha256` — checksum of the ZIP
   - `iet-v1.8.0.release-manifest.json` — copy of the package's manifest
4. Verify the checksum before uploading: `sha256sum -c iet-v1.8.0.zip.sha256`.

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

**Since v1.2.0:** Content uses per-type limits (images 15 MB, documents 25 MB,
audio 50 MB, video 100 MB for ordinary uploads, `MEDIA_LIMIT_CONTENT_*_KB`).
Lesson **video and long audio** are uploaded **in parts** through
`/content-uploads/...`: each part is smaller than the host limit (the part size
is lowered automatically below `upload_max_filesize`/`post_max_size`), so a
79 MB or 1 GB lesson does not depend on `post_max_size`. The maximum is
`MEDIA_RESUMABLE_MAX_VIDEO_KB` (default 1 GB) and `..._AUDIO_KB` (500 MB).
Partial uploads are kept in `storage/app/private/upload-sessions` (shared
storage) and removed by the hourly `media:prune-upload-sessions` schedule.
Video is played from the original file with byte-range streaming (seeking
works); the poster frame and duration are captured by the uploader's browser,
because the shared host has no FFmpeg. `php artisan about` shows a **Video**
section.

Before v1.2.0:

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

   Once after upgrading to v1.4.0 (safe to repeat; it only adds playback
   copies next to existing videos and never changes the originals):

   ```bash
   /usr/local/php-8.4/bin/php artisan media:faststart-existing --limit=500
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

Additional checks for v1.2.0:

9. **Today**: compact schedule, no `+3:30` beside times; hovering or tapping a
   date shows the Gregorian date and timezone.
10. **Planner evidence**: an attached image/video/PDF shows as a preview and
    *Open evidence* shows the post-like page.
11. **Content**: *Create → Lesson*, write, insert an image, *Publish*, open it.
12. **Lesson video**: insert a video of 50–100 MB; refresh the page halfway;
    choose the same file again — it continues; after publishing it plays,
    seeks, and shows its poster. Add an `.srt` or `.vtt` caption file.

Additional checks for v1.3.0 (use a private browser window as the guest):

13. **Public post**: in a published lesson choose *Share → Make public*. In a
    private window `/public` lists it, the home page previews it, and it opens
    and plays its video without logging in. *Make private* — it is gone (404).
14. **Invitation link**: *Share → Create link* for a name with a message. In a
    private window the link shows the greeting, the message and the content;
    the panel counts the view. *Turn off* — the link now says it no longer
    works.

Additional checks for v1.4.0:

15. **Money is gone, Bookkeeping is there**: the menu shows *Bookkeeping*;
    `/money`, `/accounting` and `/exchange` return 404.
16. **Personal book**: *Bookkeeping → My personal book*, record *Spent* 350,000
    (try Persian digits) with a receipt photo; the Cash wallet and the month
    totals change; *Undo* adds the opposite entry.
17. **Shared book**: create a shared book, add a second test user by username,
    record a debt of 40,000,000; log in as the second user — Today shows it
    waiting; confirm it; record a 10,000,000 payment with an image; the first
    user confirms and the debt shows 30,000,000 left.
18. **Video over VPN**: open a public lesson with a video on a phone with a
    VPN on; it should start within a few seconds and seek without waiting for
    the whole file.

Additional checks for v1.5.0:

19. **Version**: the sidebar shows the site name with `1.5.0`; clicking it
    opens *What's new*.
20. **Delete / rename**: on *Content*, open a test item's `⋯` menu → *Rename*;
    then *Delete permanently*, type the title, confirm — it is gone. On an
    item someone else commented on, the dialog offers *Archive instead*.
21. **Sections**: in the editor give a paragraph the type *Question*,
    publish; in the reader the paragraph shows *Answer*; answer it; as the
    author open the answers and *Mark as correct*.
22. **Menus on a phone**: select text in a lesson — the actions appear at
    the bottom of the screen; the note box opens as a bottom sheet.

Additional checks for v1.6.0:

23. **Wizard**: *Planner → New plan*; choose *Focus time*, a title, *Next
    week*, tap two days with *Pick several days*, drag 09:00–11:30 on the
    timeline, pick a label and colour, create. Both days appear in the list as
    one card.
24. **Meeting**: *New plan → Meeting*, add two times, invite a second test
    user and a guest by name; open the guest link in a private window and
    answer; as the second user answer *Yes* to one time; as the organizer
    *Choose* it — it appears in both planners; the guest page offers the
    calendar file.
25. **Repeating plan**: a daily routine shows once in *List* with "+N more",
    and its page shows three occurrences in full.

Additional checks for v1.7.0:

26. **Due date**: *New plan → Due date*, title, a day next week, *Every
    month*, keep *Important* on, create. Today shows it under *Don't
    forget*; *Done* removes it.
27. **Device notifications**: on the notifications page *Turn on*, allow the
    browser prompt, then *Send a test* — a notification appears (also with
    the site tab closed). If it says it could not be delivered, the host
    cannot reach the push service; email reminders still work.
28. **Phone time picker**: on a phone the timeline is full width; the ‹ ›
    arrows move hours and 24 h / 12 h / 4 h zoom.

Additional checks for v1.7.1:

29. **Phone layout**: on a phone open Today, Content, a content studio page
    and the profile page — nothing is cut off at the edge, the page cannot be
    dragged sideways, the side menu appears only with the ☰ button, and the
    profile picture in the header is a square.
30. **Caching**: in the browser's developer tools (Network), a file under
    `/build/assets/` shows `Cache-Control: public, max-age=31536000,
    immutable` and the page is served with `Content-Encoding: gzip` (or `br`).

Additional checks for v1.8.0:

31. **What I can do**: account menu → *What I can do* → *Add a skill*; search
    a skill in Persian, then open an area, add your own category with
    *Suggest it for everyone*, set level/years/confidence and save.
32. **Review**: *Administration → Category suggestions*: approve the
    suggestion; the skill now shows the shared category.
33. **Business team**: on a business you manage, add a skill to a team member.

Only after this real-host browser acceptance should the release be tagged
`v1.0.1`.
