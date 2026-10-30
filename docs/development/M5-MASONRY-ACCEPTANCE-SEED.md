# M5 masonry experience — isolated local acceptance demo

**Status: verified on disposable MySQL on 2026-10-29.** The original seeder and corrected chronological replay both executed through the real domain actions. The regression test verifies both username logins, Deal page access, scenario quantities, history order, financial direction and duplicate rejection. The targeted M5 suite passed: 14 tests, 295 assertions.

## Scope

* Two distinct, newly created accounts: `iet-demo-employer` and `iet-demo-mason` (both use the explicitly supplied demo password).
* Employer's public masonry Need, invitation/acceptance, and accepted Agreement, with two rates:
  * Four days of preparatory labor at **1,800,000 Toman/day**.
  * Seven masonry reports at **300,000 Toman/square metre**, estimated overall work **650 square metres**.
* One partially accepted day (17 reported, 15 accepted); one day awaiting inspection (9 reported).
* Two confirmed payments on account (2,500,000 and 3,000,000 Toman), applied by the normal FIFO credit rules. The 4,000,000 Toman request is linked to its 3,000,000 payment and remains open for 1,000,000.
* Confirmed earnings: 4 × 1,800,000 + 81 × 300,000 = **31,500,000 Toman**. Received: **5,500,000**. Remaining: **26,000,000**. Pending 9 m² is **2,700,000** of reported work, excluded from earnings and debt. Employer and mason ledger positions are -26,000,000 and +26,000,000.
* Both accounts receive Market, Deals, Bookkeeping and Messages access through the normal feature-surface service, with Persian locale and Tehran timezone. Enter the username in the login identifier field; both use the supplied password. Use separate browser profiles/private windows.
* All records are simulated, beginning approximately 14 days prior to execution. There are no real receipt files, photographs, recorded voices, conversations or genuine events. After seeding, add your own through the application so those workflows can be exercised.
* The daily wage and measured masonry phases share **one** accepted Agreement containing separate rate categories; they are not two separately signed Contracts.

## Guardrails

* Seeder is **not** registered with `DatabaseSeeder`; it requires an explicit command.
* Local environment only, opt-in flag, 16+ character demo password.
* Refuses duplicate reserved usernames or Deal title before writing any records.
* Uses Laravel domain actions rather than inserts of fake acceptance/transaction states.
* Wraps domain operations in a DB transaction; unexpected exceptions should rollback the scenario's database writes. Media and external side effects of domain actions are not guaranteed transactional.
* A normal development database refresh requires explicit owner authorization and a checked effective connection immediately before destruction. For this acceptance run the owner explicitly authorized refreshing all disposable development data in `C:\laragon\www\EveryThing`; the checked target is `EveryThing-system` on `127.0.0.1:3306`. This authorization does not extend to any other database or environment.

## First validate on a disposable database

1. Create a separate disposable MySQL database and set the application configuration to point *only* to that database for the Artisan process. Confirm `DB_DATABASE` using `php artisan db:show` or `php artisan config:show database.connections.mysql.database`. Use `php artisan config:clear` if configuration caching prevents overrides.
2. Apply migrations on the disposable database, and bootstrap required local account/permission defaults if necessary.
3. Explicitly set these values in the shell (never commit the secret):
   - `IET_MASONRY_DEMO_ALLOW=1`
   - `IET_MASONRY_DEMO_PASSWORD=<new random secret of 16+ characters>`
4. Run `php artisan db:seed --class=Database\\Seeders\\MasonryExperienceSeeder`.
5. Verify the Need, Deal and Agreement tabs under the printed relationship UUID; log in separately as employer and mason.
6. Check counts and invariants: 11 reports, 10 review decisions (one pending), 4 accepted day-rate reports, 6 accepted masonry reports, one partial acceptance, two confirmed on-account payments and a payment request. Verify no duplicate obligations, every money entry has the right payer/payee, and the two accounts see complementary balances.
7. If an action fails, capture the exact error/stack and repair the seeder or domain issue; don't bypass policies or write fake financial records directly.
8. Rehearse idempotence: rerun should refuse the duplicate demo Deal without modifying data.
9. Drop only the explicitly named disposable database after reviewing evidence.

## Human acceptance walkthrough

A. As employer: Market → open original Need → related Deal; inspect signed Agreement, two rate categories, four daily reports and seven masonry reports in Work. Verify one partial acceptance and one awaiting review.

B. As mason: inspect your measured progress, earnings, request and two received payments. Add one new report dated today, with a photograph or voice note if supported.

C. As employer: accept part of the new report, post a material-delivery instruction in the conversation, and report a small additional payment.

D. As mason: confirm receipt; verify Bookkeeping balance and advance application agree with the Deal summary. Leave one report disputed to check honest next-action prompts.

E. Check Persian RTL, English LTR, desktop/mobile, accessibility and visible load times. Report any contradictory labels, unexplained actions, missing media previews, spinner timeouts, and misleading balances.

The seeder is intentionally separate from the default bootstrapping process so it can be reviewed or removed without affecting production.

## Verification evidence

Disposable target: `iet_masonry_acceptance_disposable_20261029`, MySQL at `127.0.0.1:3306`. Verified the pinned original Need, two active verified accounts, two exact-version Contract acceptances, 11 reports (4 daily + 7 measured), 10 reviews, 17 → 15 m² partial acceptance, one pending 9 m² report, ten confirmed obligations, two confirmed payments, complementary ledger balances, chronological financial history and repeat refusal with unchanged record counts. No policy, authentication or financial state was bypassed. Pint and `git diff --check` passed.

Run the regression test with `php artisan test --compact --filter=MasonryExperienceSeederTest`. The normal test configuration uses in-memory SQLite; MySQL was independently exercised against the disposable database. This is execution and HTTP/component coverage; visual browser acceptance remains the walkthrough below.
