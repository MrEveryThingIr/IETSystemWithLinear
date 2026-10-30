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

## Known gaps (not blockers)
- Audio books and podcasts (chapters, RSS, loudness processing) are not built; they need ffmpeg on the server.
- Browser walkthrough (Persian RTL, mobile, dark mode) of the newest screens is still to do by a person.
- Progress against an Agreement estimate is per duty, not summed across Agreement versions.
