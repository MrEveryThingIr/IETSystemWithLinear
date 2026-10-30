# Release readiness — 2026-10-30

Branch `feat/planner-content-contacts` already contains `feat/m5-deal-money` and `feat/m5-masonry-acceptance-seed` (linear history), so one merge brings the whole line.

## What ships
- M5 Deal money: advance-credit accounting, one daily report, accepted work recorded once, role-aware Money tab, Agreement work rates, inline media on Deal work.
- Content: "My contacts" audience, "From my contacts" feed, Plan and occurrence links.
- Deals: "Schedule this work" creates a Planner plan already linked to the work, from the Work tab.

## Migrations to run (in order, after a backup)
`2026_11_13` … `2026_11_18` (structured terms, principal identity, derivation key, accepted quantity) and `2026_11_20_000000_create_plan_content_links`. All are additive. No data backfill is needed; historical credit is never auto-applied (see `M5-ACCOUNTING-CORRECTION.md`).

## Before deploying
1. Back up the database and `storage/app`.
2. `php artisan migrate --force`, `npm run build`, `php artisan optimize`.
3. PHP: raise `upload_max_filesize` / `post_max_size` if members will upload large media (default Content limit is 12 MB).
4. Restart queue workers.
5. Smoke test with two accounts: Agreement with work rates, daily report, acceptance, payment, confirmation; schedule a task; share an entry with contacts.

## Full-suite result (2026-10-30)
1289 of 1293 tests pass on SQLite. The 3 failures are `PlannerRemindersTest` push-encryption tests: `openssl_pkey_new` returns false on this Windows PHP (missing `openssl.cnf`), an environment issue unrelated to the code. Check on the server: `php -r "var_dump(openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]));"` must not print `false`, or device push reminders will not work.

## Employment and real jobs
Agreement work rates cover monthly salary, daily or hourly pay, overtime and piece work in one Agreement. Advances can be paid or asked for before the first report and are applied once to accepted work. Progress adds up across Agreement versions.

## Known gaps (not blockers)
- Audio books and podcasts (chapters, RSS, loudness processing) are not built; they need ffmpeg on the server.
- Browser walkthrough (Persian RTL, mobile, dark mode) of the newest screens is still to do by a person.
