# M2 Adaptive Intention Composer — shared Claude/Codex handoff

## Current status

In progress. Owner acceptance, merge and release are **not established**.
This file is the continuation record; do not restart from the older
`feat/m2-business-foundation` branch.

## Git and dependencies

- Base: `feat/m1-one-home`, `afc52dc28d072c98ac66147acf92cb07bcb34916`.
- Feature: `feat/m2-adaptive-intention-composer`.
- C1 implementation commit: `3381e806aeb989334404def767c7d5b81285033e`, pushed.
- Latest implementation commit: use `git log -1 --format=%H` on this branch.
- M2 PR: not created yet; initially target `feat/m1-one-home`.
- M1: [PR #85](https://github.com/MrEveryThingIr/IETSystemWithLinear/pull/85), open and unmerged when checked October 25, 2026. Acceptance not inferred.
- Living Planner: [PR #80](https://github.com/MrEveryThingIr/IETSystemWithLinear/pull/80), separate draft at `e01565460f841660f95c9b37e002dd3ab655b727`; untouched.
- Integration: `e2f6afe6907b10571a8a56354c41cc1b16a44db6` when fetched.
- Linear: not created; no messaging or issue updates authorized by this handoff.

## Authoritative inputs

The owner supplied the October 25 handoff and explicitly authorized completing M2.
The supplied architecture and M2 proposal are preserved verbatim in `approved/`.
The proposal refines M2; M3–M8 are scope boundaries, not authorization to implement
those milestones now. Repository compass and development circuit were read.

## Checkpoints

| Checkpoint | Code | Engineering | Browser | Owner |
|---|---|---|---|---|
| C1 Typed details/version foundations | Implemented | 34 tests / 182 assertions; retry regression 7 / 27; PHPStan clean; SQLite and MySQL rollback/reapply passed | Not applicable to storage | Not accepted |
| C2 Composer | Implemented and routed at `intents.compose` / `intents.compose.edit` beside the legacy forms (legacy untouched) | 7 interaction tests incl. route/authorization; translation parity; PHPStan | Oct 25: describe → draft (URL moves to the draft) → publish, desktop/mobile/dark/Persian RTL on a disposable SQLite DB | Not accepted |
| C3 Media/preview | Implemented: media upload/order/visibility in composer, shared audience-aware presentation (`intents.show`), media history migration | ComposerMediaTest + ComposerPresentationTest passed (Market suite 23 tests / 93 assertions) | Live preview verified; real file upload not yet driven in browser | Not accepted |
| C4 Publishing/history | Implemented: owner-only history list and version view (`intents.versions.show`), restore-as-draft (`RestoreIntentVersion`), versions immutable, Deal source versions untouched, legacy edit refuses to overwrite pending composer work | Market suite incl. restore/authorization/immutability tests | Restore not yet driven in browser; history list renders | Not accepted |
| C5 Discovery/Business | Implemented: one typed Directory filter over published search values (whitelisted keys, bound values), property facts of a catalog offer become details (floor area, rooms, floor), read-only From-your-catalog card, catalog-owned terms enforced server-side, explicit Use the new catalog version | ComposerBusinessCatalogTest (3 tests) + Business/Market/Directory/Intent filter run 190 tests passed | Filter UI verified light theme desktop | Not accepted |
| C6 Acceptance/hardening | In progress: full regression, real browser upload, MySQL rehearsal done; MariaDB covered by the CI legacy-mariadb job rather than a local install | See Evidence below | Upload, preview, draft/publish, mobile dark, Persian RTL, light desktop; accessible-name sweep of composer clean | Not accepted |

## Inventory and compatibility boundary

- Canonical authority: `ActorProfileIntent` and its existing create/update/normalize Actions.
- Existing creation route: `intents.create`, six-step `Intents/Create`.
- Existing editing: `Profile/Intents`; both remain until composer equivalence is verified.
- Existing media: `ManageIntentMedia`, `CreateContextAsset`, intent media controller, Asset responses.
- Existing discovery: `Intents/Directory`, `IntentMatchFinder`, publisher/visibility scope.
- Existing Business publication: `BusinessMarketService` and immutable listing/price versions.
- Existing money: `Toman`, now wrapped at the Market boundary by `MarketMoney`.
- Existing typed Content rules and canonical hashing are reused by detail validation/snapshots.

## Storage decision

The canonical record gains a `working_copy` JSON for staged core fields and typed
details, alongside `published_version_id`. Published-facing core columns remain
unchanged while edits are pending. This keeps existing indexed discovery/matching
on the published terms and avoids leaking pending price/audience changes through
legacy queries. It is one authority on the existing record, not another Need/Offer.
Publish atomically applies the working copy, appends an immutable version and
builds only whitelisted non-private search values. Discard clears pending work.
Legacy write Actions keep immediate publication behavior until their UI is replaced;
the composer must use `SaveIntentWorkingCopy`, not directly update live core terms.

New schema: `intent_details`, `intent_versions`, `intent_search_values`,
`intent_detail_templates`; publication pointer/working copy; media visibility;
Deal source-version reference. The migration backfills old active/paused/closed
records in batches without converting amounts or changing existing timestamps.
Unknown metadata remains on the source record. Down migration drops only additions.
No migration has been run against the owner's normal database.

Private fields/files must never enter public indexes or unfiltered presentation.
Reference fields need viewer authorization on output as well as author authorization
on input. Media visibility columns alone do not complete endpoint enforcement.
No file-storage replacement is planned. Monetary abstractions outside Market stay.

## Outstanding work and risks

C1's populated rehearsal passed on MySQL 8.4.3: 119 migrations, original fields
preserved, Deal source version backfilled, rollback/reapply successful, all tables
InnoDB. Evidence: ignored `storage/release-acceptance/m2/mysql-rehearsal.json`.
SQLite populated rollback/reapply also passed. MariaDB and remote CI are covered in
the acceptance runbook below. The rehearsal created a new `iet_m2_rehearsal_*` database; the
owner's configured database was not modified. Publication retry compares content
without generated detail UUIDs, with a regression assertion.

C2–C6 remain: canonical composer, legacy adapters,
shared audience-aware presentation, media/progress/retry/privacy, templates,
publication history, typed filters, Business/property adapters, full regression,
remote exact-head CI and real browser scenarios. Do not describe this foundation
as a finished user feature. Composer strings have parity in four languages;
downstream media/history/discovery strings must also maintain that parity.

The C2 component now has four-language controls, category/skill picker reuse,
publisher authority, typed inputs, field ordering/grouping/privacy, shape-only
personal/business templates, localized core numbers, and optional advanced
schedules/routes. Six interaction tests plus seven foundation tests passed
(13 tests / 54 assertions). Existing routes still use the old editors: do not
claim replacement or browser acceptance yet. Reference selection currently scans
the latest 100 candidates and applies viewer policies before showing labels;
replace this bounded prototype with searchable scoped queries before acceptance.
Legacy non-IRT price edits preserve original terms unless explicitly replaced.
Draft kind can change before any publication; published kind stays immutable.
Legacy update refuses to overwrite/publish a pending composer working copy.
Shared exact preview, media, historical presentation and discovery are outstanding.
Latest compatibility run: 40 tests / 210 assertions passed; subsequent focused
foundation run: 9 tests / 33 assertions passed (adds Deal version pinning and
legacy pending-publication regression cases). PHPStan clean; touched PHP Pint clean.

## Evidence (Oct 25, branch head in git log)

- Full suite: 1105 tests, 1105 passed, 10490 assertions (with OPENSSL_CONF set). PlannerRemindersTest: 3 failures without it were environmental. PHP OpenSSL on this Windows machine cannot create EC keys without `openssl.cnf` (`error:80000003`); with `OPENSSL_CONF=<php dir>/extras/ssl/openssl.cnf` all 6 pass. Not caused by M2 code.
- Migrations: MySQL 8.4.3 disposable `iet_m2_rehearsal_c6` migrate / rollback 2 / reapply passed, all InnoDB, then dropped. MariaDB 10.6 (MyISAM default) is verified by the CI job, not locally.
- Browser (disposable SQLite DB, never the Laragon database): describe, draft, URL moves to the draft, real PNG upload with progress, publish, shared preview, anonymous media blocked for a signed-in-only post, mobile dark, Persian RTL, desktop light, Directory detail filter.
- Known limits: single typed filter at a time; Arabic/Chinese screens covered by parity tests only; legacy create/edit forms remain by design until owner accepts equivalence; reference picker still scans the latest 100 candidates.

## Owner acceptance runbook (Laragon, existing database)

Production engine: MySQL/MariaDB on shared hosting with a MyISAM server default and legacy
TIMESTAMP defaults (`docs/PRODUCTION_DEPLOYMENT.md`). CI already carries a
`legacy-mariadb-portability` job (MariaDB 10.6, MyISAM default); no local MariaDB is needed.

**Remote CI at head `214b8b46` (GitHub check runs, read Oct 25 after push):** PHP 8.4 / Node 22,
Release blockers ubuntu and windows, and Shared-host MariaDB all completed `success`.
Local evidence (above) and CI evidence are separate; CI re-verifies only commits that are pushed.
Each later doc-only commit needs its own CI read before acceptance.

**Migration risk before you run `php artisan migrate`:**

- Two new migrations, both additive: `2026_11_10_000000` creates `intent_details`, `intent_versions`,
  `intent_search_values`, `intent_detail_templates` and adds nullable columns to `actor_profile_intents`
  (`published_version_id`, `working_copy`), `actor_profile_intent_media` (`visibility`) and
  `relationships` (`originating_intent_version_id`); `2026_11_10_000100` adds `deleted_at` to intent media.
- It backfills one version 1 for each existing active, paused or closed post and pins existing Deals to it.
  No amounts, timestamps or files change. Expect a pause proportional to the number of posts.
- Rollback drops the new tables and columns, so it deletes version history, working copies and
  detail values created after migrating. Treat it as a development tool, not a way back after acceptance.
- Before migrating: back up (`mysqldump --single-transaction --routines <db> > backup.sql`), then run
  `php artisan migrate --pretend` to read the SQL, then `php artisan migrate`.
- Draft posts are new rows with status draft; they stay out of the Directory, matching and Deals until published.

**Browser acceptance (about 10 minutes).** Pull the branch, `composer install`, `npm ci && npm run build`,
migrate as above, enable the market surface, sign in, then:

1. `/intents/compose`: type one sentence, Save draft. The address moves to the draft; reload keeps it.
2. Details: add Floor area and one custom detail; set one detail to Only me; check the preview audience switch.
3. Photos: upload a photo, tick the rights box, save; reorder or remove it.
4. Publish, open `/intents/{id}`, then sign out and confirm the audience rule and private detail are respected.
5. Edit and publish again; History shows two versions; View one; Restore the first as a draft; Discard it.
6. `/intents`: Detail, Floor area, minimum, and check it narrows results; Clear filters.
7. Business owners: Offer a property catalog item to the market; the catalog card is read-only; edit the
   catalog item and publish it; the post offers Use the new catalog version.
8. Repeat steps 1 and 3 at phone width, in dark theme and in Persian.
9. Existing `/intents/create` and the profile editor still work (legacy forms remain by design).

**Working-cost guidance for the next sessions.** Sonnet is fine for implementation and review. Use Haiku
at low effort for mechanical edits, translation parity, doc updates and simple checks. Keep higher effort
for design decisions, data-integrity or authorization changes. Run focused tests while building and the
full suite only at gates.

## Continuation

1. Fetch origin and inspect status/worktrees before switching; preserve local work.
2. Continue this branch based on M1; never force-push or rewrite M1.
3. Read this file and `MILESTONE-PROGRESS.md`; inspect the first incomplete checkpoint.
4. Run `php artisan test --do-not-cache-result tests/Feature/Market/ComposerFoundationTest.php`
   and relevant existing Market/Relationship tests; use PHPUnit's isolated SQLite.
5. Rehearse migrations only on explicitly named disposable SQLite/MySQL/MariaDB targets.
6. Commit/push coherent checkpoints and replace pending evidence with actual results.
7. M2's PR initially targets M1. Retarget only after actual acceptance/merge and a
   deliberate integration review. Never auto-merge, deploy or start M3.
