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

## Usability refinement: field library and minimal composer (branch `feat/m2-category-aware-composer`)

Owner feedback (twice): the composer still felt like a long predefined form. Branched from verified M2 head
`d94df0be`; PR #86 is untouched and no refinement PR is open. Roadmap unchanged (an M2 refinement, not a milestone).

**Principle:** a rich library, a minimal form. Nothing is inserted automatically; the person chooses.

- **Initial form:** Need/Offer, title, description, topic (optional), audience, Save draft, Publish. Nothing else.
- **Three separate ideas:** *suggested* (chips, from topic, Need/Offer, arrangement and what is already chosen),
  *selected* (what is actually in this post), *applicable* (a rule: a rent-only question on a sale is flagged
  "does not apply" and excluded from publication and search, but stays in the draft; nothing is deleted).
- **Topic hint:** words in the title/description (four languages) suggest a topic as a chip; the person applies it.
  Recommendations lean on the hinted topic without choosing it.
- **Add field:** one entry point opening a searchable picker (modal; bottom sheet on phones; Escape and keyboard work):
  recommended, recently used (from the publisher's past posts), my saved items, price/place/time terms, all fields by
  domain, create your own. Search matches localized names, synonyms, domain names and English in other languages;
  Persian/Arabic digits and letter variants are normalised.
- **One meaning per concept (`IntentTerms`):** price (budget for a Need), place (one "where"), when, route, quantity,
  category, media, importance are optional blocks the person adds; each owns specific core columns, removing a block
  clears them, and blocks never chosen are never published. The duplicated "advanced" section is gone (subject and
  arrangement come from topic and sale/rent). Catalog-linked posts keep price/place/quantity locked.
- **Library:** `IntentDetailCatalog` now holds 96 typed fields in 14 domains (general, property, vehicle, device, service,
  lesson, transport, work, activity, hospitality, events, creative, agriculture, industry) with type, unit, options,
  search eligibility (`filter`, null = not searchable), domain, provenance (`research`, `schema`, `house`, `community`)
  and since-version. Labels, synonyms, options, units and domain names are in the four languages with parity tests.
  `IntentProfiles` is purely a relevance policy (fields and terms per topic with conditions). Money, schedule, place and
  Deal terms stay canonical columns, never fields.
- **My fields, groups, templates:** save one personal field, a group of fields, or a whole composition (fields + chosen
  terms + topic) as structure only (no answers, no files); apply adds only what is missing and never overwrites
  answers; rename and delete; owner-scoped (person or business).
- **Per-field privacy** (private fields never reach search or public presentation); reorder; remove.
- **Community fields and approval:** a custom field can be suggested to everyone (only name, kind, unit, choices are sent,
  never an answer; 20 open suggestions per person). Lifecycle proposed, reviewed, approved/rejected, published.
  Superadmin page `platform/fields` (capability `ManageConcepts`, like category review): duplicate detection by name/synonym
  in any language, edit names and synonyms in four languages, domains and choices, approve and publish as an immutable
  numbered version (the previous version is retired), reject with required note (optionally as duplicate of an existing
  field), retire a community field (hidden from pickers; posts using it keep working). The search column is derived from the
  type, never from the request. A built-in key keeps its type, unit and filter forever; review may improve its words and add
  choices only. Published posts keep their snapshot, so earlier meaning is preserved. Proposals cannot touch canonical terms.
- **Schema (additive, `2026_11_11_000000`):** `intent_field_definitions`, `intent_field_proposals`, and `kind`, `topic`,
  `terms` on `intent_detail_templates` (existing templates become `composition`). Rollback drops only these.
- **Reuse elsewhere:** the picker, field renderer, library, search and approval flow are generic components over
  `IntentDetailCatalog`; profiles, Business Catalog, content or intake forms can adopt them without replacing specialist
  financial, contractual or scheduling models (not done in this branch).
- **Evidence (Oct 25):** full suite 1131 tests / 12387 assertions passed (OpenSSL config set); Pint and PHPStan clean; new tests cover the library, search in four languages, minimal initial form, suggestions versus selection, applicability, terms, templates, privacy, governance and the review page. Browser on a disposable SQLite database: Offer vehicle (hint, conditional Mileage, picker search "kilo" finds Mileage and Power), Need plumber (Budget, Preferred location, When as separate blocks), Persian RTL phone picker (bottom sheet, hint from Persian words), admin review with duplicate detection. Not browser-checked: light/dark of every screen, Arabic and Chinese screens, real upload in this branch (unchanged from M2 evidence).
- **Limits:** community choice options display as typed (no per-option translation); proposer cannot yet see status
  of their suggestions; no admin editing of built-in profiles or terms; one Directory detail filter at a time; the reference
  picker still scans the latest 100 candidates; Arabic and Chinese screens are covered by parity tests, not screenshots.

## Semantic field intelligence, community improvements and final UX refinement

Same branch (`feat/m2-category-aware-composer`); PR #86 untouched, no new PR. M2 is **not** marked accepted.

- **Meaning:** vehicle fields are unambiguous (Make, Model, Year of manufacture, Trim; vehicle class vs body style;
  `body_type` retired in favour of `body_style`). Examples are localized (e.g. «مدل خودرو: پژو ۲۰۶، تیپ ۵»,
  «سال ساخت: ۱۳۹۶»). Year is its own type (`YearSpan`: Persian/Gregorian aware, legacy values guessed by magnitude).
- **Typed constraints (`DetailValue`):** exactly / at least / at most / between / one of, only where the type and side
  allow. A Need wish that a post violates excludes it; an unknown value never counts as a match and is listed in
  `IntentMatchResult::$unknown`. Canonical Deal terms (price, place, time) stay separate columns.
- **Notes and validation:** optional per-field note ("Add note"). Free text such as "at least 8 months" is offered back as a
  structured reading ("Use “at least 8 months”") and applied only on confirmation (browser-verified, including the input
  showing 8 afterwards).
- **Suggest an improvement:** discreet entry in the field menu, group menu and library; structure only (never answers or
  files). Types cover wording, ambiguity, type/unit, options, synonyms, new category/group, group membership, conditional
  visibility, Need/Offer applicability, input control, example, duplicates, templates. People see their own suggestions and
  statuses at `/intents/field-suggestions`. Moderation in `platform/fields`: original vs proposed, context, aliases,
  compatibility notes, approve/reject with feedback; approval writes a new immutable version (type/unit changes become
  successor keys; duplicates merge via aliases + retire).
- **Shared taxonomy:** domain → group → field by stable keys; a field may sit in several groups with one definition;
  append-only memberships (latest row wins).
- **Field / Group / Template:** Add field only finds or creates fields; groups are personal (add, remove, reorder, one
  nesting level, reusable); “Save as template” is on the main actions and “Use template” near the start. Templates and
  groups never hold answers, files or private data; applying never overwrites answers.
- **Published cards:** one presentation strategy (`IntentPresentation`, `x-market.intention-presentation`, full/card
  density) for detail page, directory and related lists; privacy and version snapshots respected.
- **Legacy entry points:** `/intents/create` now redirects to the composer; the classic form stays at
  `/intents/create/classic`; nothing was deleted. Services, Interests, Businesses and Deals keep their specialist models.
- **Schema (additive, pending on the owner's database):** `2026_11_11_000000_create_intent_field_library`,
  `2026_11_12_000000_add_constraint_columns_and_field_taxonomy`. Rehearsed on populated disposable MySQL. Rollback caveat:
  `intent_detail_templates.kind` rollback collapses kinds. Back up first and run `php artisan migrate --pretend`.
- **Evidence:** full suite 1164 tests / 13742 assertions passed (OpenSSL config set), focused suites 185 passed, Pint and PHPStan clean (code at `736cecd3`).
  Browser (disposable SQLite): offer vehicle, need with interpretation chip, picker and search, suggestions.
- **Limits:** picker opening is slow on the dev server; community option text is not per-option translated; Arabic and
  Chinese verified by parity tests, not screenshots.

## Navigation and reuse acceptance fixes

Owner found two defects; both fixed on the same branch (PR #86 untouched, no new PR, M2 not accepted).

- **One editor for Needs and Offers.** My Items "Change" opened the legacy profile editor. Audit result: the only
  edit entry points left on the old editor were My Items (`profile.edit?intent=`) and the Needs/Offers card on the profile
  page. Now: My Items, the profile card and the directory all open `intents.compose.edit`; the old
  `profile?intent=<uuid>` link redirects (owner-checked) to the Composer; "Add" on the profile card opens the Composer.
  The legacy component, its pause/close actions, `intents.create.classic`, photos and all data stay. Interests, Abilities
  and Business Catalog keep their specialist editors.
- **Where things are (My Items, one page, no new menu entries):** *Offers and needs* has a filter —
  Published / Drafts / Paused / Closed, each with a count (`?show=`); drafts open in the Composer, closed items open
  their read-only page. *Saved templates and groups* (`#saved`) lists composition templates and field groups with side/topic,
  field count, preview, "Start a post", rename and delete. Saved single fields stay in the Add-field picker.
- **Templates are findable.** "Use a template" is a visible button directly under the page title on every new
  composition (it explains itself when none are saved), listing all saved templates (current side first) with side/topic
  badges, preview and "Use"; a template from the other side asks, then switches side. "Save as template" stays among the
  main actions. Starting from My Items (`/intents/compose?template=<uuid>`) applies it once; someone else's uuid is ignored.
  Answers, notes and files are never saved or overwritten.
- **Field picker speed:** the taxonomy tree issued a schema query per group; checks are now memoized per request
  (tree build 2.2 s → ~0.1 s on the Laragon MySQL; picker opens in ~1.5 s on the single-threaded dev server, down from 2.3 s).
- **Evidence:** `NavigationIntegrationTest` (5 tests) plus adjusted `PersonalGroupsTest`; browser (disposable SQLite):
  save template → leave → My Items shows it → Start a post applies 2 fields and topic; draft listed under Drafts and "Change"
  opens the Composer; mobile + dark template menu without horizontal scroll; suggestion modal opens from the library.
  Full suite 1169 tests passed, Pint and PHPStan clean. Not browser-checked: admin review page this round (covered by tests; the browser user is not an admin), Persian screens.

## Owner migration evidence and final M2 gate (Oct 25)

- **Owner database:** the owner ran `2026_11_11_000000_create_intent_field_library` and
  `2026_11_12_000000_add_constraint_columns_and_field_taxonomy` on the normal Laragon MySQL `EveryThing-system`; both reported DONE.
  The earlier "templates don't save" symptom was the schema being behind the code and is resolved; saved templates are present.
  Read-only verification afterwards (no data changed): `migrate:status` has 0 pending; `intent_detail_templates` has `kind`,
  `topic`, `terms`, `parent_id`, `context`; the five library tables exist; `intent_search_values.value_number_min` exists;
  the 5 existing templates were classified `composition` by the migration. These migrations were not repeated.
- **No false success:** `Composer::storeTemplate` now reports success only for a row that is really stored; a vetoed insert or a
  database error raises a validation error (`composer.template_failed`, four languages) and no notice. Regression:
  `NavigationIntegrationTest::test_a_template_that_fails_to_store_is_never_reported_as_saved`.
- **Journey verification** (code at the exact pushed head; browser runs used a disposable SQLite database and clearly named test
  records, never `EveryThing-system`):

| Journey | Evidence |
|---|---|
| Create and save a draft | Browser (draft "Peugeot 206 for sale" saved) + `ComposerInteractionTest` |
| Edit from My Items in the Composer | Browser (Drafts filter → Change → composer URL) + `NavigationIntegrationTest` |
| Publish and view the page | Browser (Published, page shows "Published as version 1", Edit/Find matches) + `PublishedCardTest` (audience, density, privacy) |
| Save, find, apply templates | Browser (save → My Items → Start a post applied fields and topic) + `PersonalGroupsTest`, `NavigationIntegrationTest` |
| Reuse groups and typed fields | Tests only (`PersonalGroupsTest`, `FieldConstraintsTest`); groups menu not re-run in the browser this round |
| Historical versions preserved | Tests only (`ComposerFoundationTest` immutable versions, history restore) |
| Suggest a field improvement | Browser: modal opens from the library; submission, moderation and "my suggestions" covered by `FieldSuggestionsTest`/`FieldGovernanceTest` |
| Permissions and privacy | Tests only (owner-only templates/groups, private fields excluded from search and cards, composer routes refuse others) |
| Legacy routes | Browser (`/profile?intent=<uuid>` redirects to the Composer) + tests (`/intents/create` redirect, `create.classic` kept) |

- **Gate:** full suite 1170 tests / 13988 assertions passed, Pint and PHPStan clean.
- **Not browser-verified:** admin review page, Persian/Arabic/Chinese screens this round, concurrency of two editors.

## M2 integration chain (GitHub state Oct 25)

Linear and clean (each branch is a strict extension of its parent; no unrelated commits):
`integration/v2` (e2f6afe6) → `feat/m1-one-home` (afc52dc2, 2 commits, PR #85) → `feat/m2-adaptive-intention-composer`
(d94df0be, 9 commits, PR #86 — its base is currently `feat/m1-one-home`) → `feat/m2-category-aware-composer`
(10 commits, 96 files). CI is green on the refinement head `f9fb1fdc` (4 checks including shared-host MariaDB and Windows).
No refinement PR exists. Draft PR to open: head `feat/m2-category-aware-composer` → base `feat/m2-adaptive-intention-composer`
(body in `docs/development/M2-REFINEMENT-PR-BODY.md`). Independent Living Planner v2 (PR #80) is untouched.

**Proposed merge plan (after owner approval only):** 1) merge PR #85 into `integration/v2`; 2) retarget #86 to `integration/v2`
and merge; 3) retarget the refinement PR to `integration/v2` and merge; 4) on `integration/v2` run the full suite and the
Laragon walkthrough; 5) never auto-merge or deploy; Living Planner v2 merges separately.

## M2 integration result (Oct 25)

Owner merged with merge commits (the repository convention): PR #85 → `2b438857`, PR #86 → `c5ea9afe`, PR #87 → `a90cb92d`
(8 checks passing). `origin/integration/v2` = `a90cb92d`; all three branches are contained in it and its tree is
identical to the previously tested refinement head `f41da3a2`. Verified on that head: full suite 1170 tests / 13988 assertions
passed (Windows OpenSSL config set), Pint and PHPStan clean, frontend build OK, `migrate:status` against the owner's Laragon
database shows 0 pending (read-only; data untouched). Smoke journeys (My Items, Composer, templates, Need/Offer edit, published
versions, legacy redirects) are covered by the suite and were browser-run on the identical tree (Persian RTL phone layout checked).
Remaining known limitations: community choice text is not translated per option; Arabic/Chinese covered by parity tests, not
screenshots; admin review page not browser-run; no production release.

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
