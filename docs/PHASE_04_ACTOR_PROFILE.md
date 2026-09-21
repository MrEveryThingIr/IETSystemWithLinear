# Phase 4 — Actor/Party and Progressive Profile

## Status

Active on `feat/phase-04-actor-profile`, starting from accepted Phase 3 closure commit `b2e5dc0a8b7cfd33ff9dbcb6af4c6f6027c7948c`.

## Objective

Build a professional, privacy-aware Profile on top of Actor identity and the completed Concept Kernel without collapsing authentication User data into the domain Profile.

The Profile should become progressively useful over time rather than turning registration into a giant form.

## Architectural rules

- User remains authentication/account identity.
- Actor remains domain participant identity.
- ActorProfile contains mutable presentation/profile information.
- User email is not a public Profile field.
- Profile data is private by default.
- Profile URLs use a non-sequential public UUID rather than Actor database IDs.
- Profile media reuses the existing private Asset/media-processing pipeline.
- Profile images are not stored as public filesystem paths on User/Actor rows.
- Concept assertions remain the semantic source for skills, interests, needs, learning goals and related meanings.
- future Admission/Profile sharing must grant only the requested facts/assertions, not expose the whole Profile.
- generic Context/Admission v2 remains Phase 5/8 work and must not be pulled forward.

## Milestones

### 4A — professional identity + profile media foundation — complete

Owner-local validation passed on `486e877`:

- Profile migrations ran successfully;
- focused Profile foundation: 6 passed / 35 assertions;
- full PHPUnit: 298 passed / 1528 assertions;
- PHPStan: no errors;
- Pint: 29 Phase 4A PHP files passed;
- Vite production build: passed;
- working tree: clean.

Includes:

- one lazily created ActorProfile per active verified User→Actor identity;
- public UUID routing;
- display name;
- professional headline;
- biography;
- free-form location text;
- HTTPS/HTTP website URL;
- visibility: private / authenticated / public;
- guest-safe public Profile rendering;
- self-only profile editing;
- navigation/account-menu access;
- multilingual Profile UI;
- private profile-image library;
- maximum 12 profile images;
- JPEG/PNG/WebP/AVIF only;
- 8 MB maximum;
- minimum 128×128 and bounded pixel dimensions;
- immutable Asset provenance and SHA-256 hashing;
- existing scan/media-processing pipeline;
- one changeable displayed profile image;
- safe clear/remove semantics;
- authorized image streaming with `nosniff` and `no-store`;
- cross-database migration rollback behavior;
- focused tests for privacy/media/ownership.

### 4B — semantic Profile + recurring Needs/Offers — implementation complete; 4B.1 temporal hardening remote-green; renewed owner-local/browser acceptance pending

4B deliberately uses concrete Profile semantics instead of introducing a speculative universal key/value fact engine.

Implemented direction:

- Concept-backed skills;
- Concept-backed interests;
- Concept-backed learning goals;
- Actor-scoped personal Concept vocabulary as fallback when no curated platform Concept matches;
- reusable platform Concepts are preferred when available;
- instance-level Profile Need and Offer declarations linked to canonical Concepts;
- multiple distinct Needs/Offers may reference the same Concept;
- one-time, ongoing, daily, weekly and monthly declaration cadence;
- recurrence interval;
- weekday/month-day schedule constraints;
- timezone;
- optional time window;
- quantity + unit;
- location;
- origin → destination route;
- optional round trip with return-day offset;
- item-level visibility;
- active / paused / closed lifecycle;
- closed declarations remain historical and cannot be reopened;
- active Need/Offer declarations synchronize coarse Actor `needs` / `offers` Concept assertions;
- Profile UI and public rendering remain purpose-specific rather than exposing generic assertion tables.
- Profile statement composition follows progressive disclosure: relationship + Concept is the minimal core; title, description, quantity, location, route, timing and visibility are opt-in facets.
- inactive facets are omitted from the form and cleared from the pending payload when removed;
- editing an existing declaration reactivates only facets already represented by stored data;
- Concept inputs suggest reusable Platform/Actor Concepts while preserving free creation of a new Actor-scoped personal Concept when no match fits.

#### Recurrence boundary

A recurring Profile declaration is a **cadence of current intent**, not a Planner schedule and not a generated set of future records.

Example:

~~~text
Need: Transportation
Route: A → B
Schedule: every Saturday 08:00–10:00
Round trip: return after 1 day
~~~

This is sufficient Profile evidence for future discovery/matching.

It does **not** create:

- Planner Occurrences;
- reminders;
- route matches;
- proposals;
- agreements;
- commitments;
- fulfillment records.

Phase 11 Planner remains authoritative for materialized Occurrences. Phase 13 remains authoritative for full Need/Offer matching. Phase 14 remains authoritative for negotiated obligations.

#### 4B.1 — temporal localization hardening

A real MySQL browser-flow failure exposed an important cross-cutting gap:

~~~text
SQLSTATE[22007]: Incorrect date value: '' for column 'starts_on'
~~~

4B.1 fixes that defect and establishes the temporal contract that later Planner, Admission deadlines, Agreements, matching and contracts must reuse.

##### Temporal invariants

Language, timezone and calendar are **independent concerns**:

- locale controls interface language, text direction and culturally appropriate formatting;
- timezone controls the local civil clock and uses IANA timezone identifiers;
- calendar controls date presentation/input only;
- date-only domain values remain canonical ISO/Gregorian `YYYY-MM-DD` values in persistence;
- changing locale/calendar/timezone never rewrites the underlying stored date;
- no timezone is inferred from language.

Locale-derived calendar defaults are product defaults, not identity assumptions:

| Locale | Default calendar | First weekday |
| --- | --- | --- |
| English | Gregorian | Sunday |
| Arabic | Gregorian | Saturday |
| Simplified Chinese | Gregorian | Monday |
| Persian | Persian/Jalali | Saturday |

Arabic users may explicitly choose Hijri/Umm al-Qura; Chinese, English or Persian users may also override the calendar independently.

Supported presentation calendars:

- Gregorian (`gregory`);
- Persian/Jalali (`persian`);
- Hijri/Umm al-Qura (`islamic-umalqura`).

Timezone behavior is explicit:

- `auto` — follow the browser/device IANA timezone and refresh it when the device zone changes;
- `fixed` — retain the explicitly selected timezone.

##### Implementation

- blank nullable temporal/quantity inputs normalize to `null` before domain validation and persistence, so empty browser fields never reach DATE/TIME/DECIMAL columns as empty strings;
- existing `users.timezone` is preserved;
- users now store `timezone_mode` plus optional calendar override;
- locale configuration carries Intl locale, default calendar and first weekday metadata;
- a reusable temporal preference resolver centralizes timezone/calendar/week-order decisions;
- Profile contains a Date & time preferences card;
- Needs/Offers use a reusable calendar-aware picker rather than the browser's Gregorian-only `type=date`;
- the picker stores ISO dates while rendering the selected calendar through the browser Intl engine;
- public Profile date/time output is localized for the viewer;
- week ordering follows locale preference;
- picker supports RTL, month navigation, keyboard arrows, Escape, Today and Clear;
- no extra JavaScript calendar dependency was introduced.

##### Remote proof

Final 4B.1 runtime head before documentation closure:

`c1ce5ccd83020b4f51b3585e2bf092e3ba66cde6`

CI passed:

- full PHPUnit: **310 passed / 1593 assertions**;
- PHPStan: **no errors**;
- Pint: **125 changed PHP files passed**;
- Vite production build: passed;
- fresh migrations, including `2026_09_21_171000_add_temporal_preferences_to_users_table`: passed;
- scheduler/queue smoke: passed;
- SQLite backup→restore smoke: passed;
- Composer security audit: clean.

Focused regression coverage proves:

- English/Arabic/Simplified Chinese default to Gregorian presentation;
- Persian defaults to Persian/Jalali presentation;
- calendar override is independent from locale;
- timezone is independently persisted;
- Persian uses Saturday-first week ordering;
- empty optional `starts_on`, `ends_on`, time-window, quantity and recurrence fields persist as `NULL`;
- a Persian Profile emits Persian-calendar picker metadata.

Because 4B.1 adds a migration and visible temporal UI, the earlier local 4B technical acceptance must be refreshed once on this final temporal candidate before 4B can close.

#### 4B acceptance proof

Owner-local technical acceptance has now proved:

1. one personal Concept is reused across skill/interest/learning predicates rather than duplicated;
2. private semantic Profile items stay private on an otherwise visible Profile;
3. a weekly transportation Need can store origin, destination, weekday/time window and next-day round trip;
4. periodic quantity Needs such as weekly rice/meat can store quantity + unit;
5. recurring service Offers can store their own weekly cadence/time window;
6. active Need/Offer declarations synchronize coarse Actor `needs` / `offers` Concept assertions;
7. pausing the last active declaration removes the active semantic summary and reactivation restores it;
8. closing preserves history and is terminal;
9. item-level private/authenticated/public visibility is honored;
10. another Actor cannot mutate the declaration;
11. no match, proposal, commitment, occurrence or financial record is created merely from a Profile declaration;
12. full PHPUnit, PHPStan, Pint, migration and Vite gates remain green.

Owner-local technical evidence on `604bbb2`:

- migration applied: `2026_09_21_170000_create_actor_profile_intents_table`;
- focused 4B suite: **8 passed / 42 assertions**;
- combined Profile foundation + 4B suite: **14 passed / 77 assertions**;
- full PHPUnit: **306 passed / 1570 assertions**;
- PHPStan: **no errors**;
- Pint across Phase 4 PHP diff: **54 files passed**;
- Vite production build: passed;
- working tree: clean.

The only remaining 4B acceptance evidence is the human browser smoke below.

Final remote CI proof before owner-local validation:

- hardened runtime head: `6dbc36c271f02f3851a7349171f77c9591d55ee6`;
- PHPUnit: **306 passed / 1570 assertions**;
- PHPStan: **no errors**;
- Pint: **113 changed PHP files passed**;
- Vite production build: passed;
- Profile Intent migration: ran successfully;
- migration/scheduler/queue smoke: passed;
- SQLite backup→restore smoke: passed;
- Composer security audit: clean.

Final hardening also proves that 4B-generated coarse Actor `needs` / `offers` summaries are provenance-owned by the Profile-intent subsystem. An independently/manual-created Actor assertion is never silently changed or deleted when a Profile declaration is paused or closed.

### 4C — sharing, completeness and Phase 4 closure

Planned:

- progressive completeness service;
- context/request-oriented field requirements;
- reusable selective sharing grants for later Admission;
- revocation/expiry semantics where appropriate;
- privacy review;
- responsive/accessibility polish;
- final focused/full validation;
- implementation report completion;
- Phase 4 human acceptance.

## 4A security/privacy decisions

- default visibility is `private`;
- archived Actors are never viewable through Profile;
- only the owning active verified User may update the Profile;
- authenticated visibility requires an active verified account;
- public visibility permits guest viewing but never exposes account email;
- display image delivery rechecks Profile visibility on every request;
- stored Profile images remain on private storage;
- images must pass the Asset readiness contract before being displayed;
- changing visibility takes effect immediately because profile media responses are `private, no-store`;
- deleting a Profile image first detaches it from Profile state, then removes the unreferenced Asset and stored file;
- profile media never receives ordinary GroupSpace authority merely to satisfy the old Asset schema.

## 4A acceptance proof

4A closes when the owner-local checkout proves:

1. one Actor receives one Profile with a non-sequential public UUID;
2. Profile starts private;
3. another Actor cannot edit it;
4. authenticated/public visibility behave correctly;
5. public rendering does not expose User email;
6. valid private image upload creates an Asset and first image becomes displayed;
7. non-image media is rejected;
8. displayed image can switch and clear;
9. image removal removes the unreferenced Asset and private file;
10. existing Group/Content/Invitation behavior is unaffected;
11. focused/full PHPUnit, PHPStan, Pint, migrations, and Vite build are green;
12. browser/mobile smoke is accepted.

## Explicitly excluded from 4A

- skills/interests UI;
- generic Profile fact engine;
- selective Admission sharing;
- profile completeness rules;
- organization/Party identity;
- generic Context;
- Admission v2;
- recommendation/ranking;
- social follower/friend graph;
- public media CDN/caching.

## Phase 4 exit gate

Phase 4 is complete only after 4A, 4B and 4C are all accepted.
