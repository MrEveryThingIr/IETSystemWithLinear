# Phase 4 — Actor Profile Implementation Report

## Status

Phase 4 is active on `feat/phase-04-actor-profile`.

- 4A — professional identity + profile media: **complete and owner-local accepted**.
- 4B — semantic Profile + recurring Needs/Offers: **implementation complete**.
- 4B.1 — temporal localization hardening: **implemented and remote-CI validated at 310 tests / 1593 assertions; refreshed owner-local/browser acceptance pending**.
- 4C — selective sharing/completeness/final Phase 4 closure: **next after 4B acceptance**.

## Starting point

- accepted Phase 3 closure: `b2e5dc0a8b7cfd33ff9dbcb6af4c6f6027c7948c`;
- Phase 4 branch: `feat/phase-04-actor-profile`;
- contract: `docs/PHASE_04_ACTOR_PROFILE.md`.

## 4A — professional identity and media

Implemented and owner-local accepted:

- one ActorProfile per User-backed Actor;
- non-sequential public UUID route;
- display name, headline, biography, location and website;
- private/authenticated/public Profile visibility;
- public Profile never exposes account email;
- owner-only editing;
- private profile-image library using the existing Asset pipeline;
- JPEG/PNG/WebP/AVIF restrictions;
- size/dimension bounds;
- scan/processing readiness;
- up to 12 profile images;
- one changeable displayed image;
- safe clear/remove semantics;
- authorized image streaming;
- responsive multilingual UI.

Owner-local 4A evidence:

- migrations applied successfully;
- focused Profile foundation: **6 passed / 35 assertions**;
- full PHPUnit: **298 passed / 1528 assertions**;
- PHPStan: no errors;
- Pint: passed;
- Vite production build: passed;
- working tree: clean.

## 4B — semantic Profile and recurring Needs/Offers

### Semantic Profile

4B reuses the Phase 3 Concept Kernel instead of creating duplicate skill/category tables.

Implemented:

- Concept-backed skills (`has_skill`);
- interests (`interested_in`);
- learning goals (`wants_to_learn`);
- localized Concept display labels;
- platform Concepts preferred when an active matching label exists;
- Actor-scoped `profile-concepts` vocabulary as a safe fallback for personal concepts;
- one Concept reused across multiple predicates;
- inherited/private visibility for semantic Profile items;
- owner add/remove/visibility controls;
- public Profile rendering that honors item privacy.

### Need / Offer declarations

Added `ActorProfileIntent` as an **instance declaration** linked to one canonical Concept.

This deliberately avoids treating `Actor --needs--> Concept` as the whole Need record, because one Actor may have multiple distinct Needs or Offers for the same Concept with different quantities, routes, schedules or visibility.

Kinds:

- Need;
- Offer / service.

Timing modes:

- one-time;
- ongoing;
- daily;
- weekly;
- monthly.

Structured constraints:

- title and description;
- quantity + unit;
- free-form location;
- origin → destination;
- optional round trip;
- return-after-days offset;
- start/end dates;
- timezone;
- recurrence interval;
- weekly weekdays;
- monthly day;
- optional time window;
- per-item visibility.

Lifecycle:

- active;
- paused;
- closed.

Closed declarations are historical and terminal rather than physically deleted.

### Circuit / cadence boundary

A recurring Profile declaration records the **cadence of current intent**.

Example:

~~~text
Need: Transportation
Route: Location A → Location B
Schedule: every Saturday, 08:00–10:00
Round trip: return after 1 day
~~~

Another example:

~~~text
Need: Rice
Quantity: 5 kg
Schedule: every Friday
~~~

And an Offer:

~~~text
Offer: Programming tutoring
Schedule: Tuesday and Thursday, 18:00–21:00
~~~

4B stores enough structured data for later discovery/matching without prematurely implementing later kernels.

It does **not** create:

- Planner Occurrences;
- reminders;
- automatic route/quantity matching;
- proposals;
- negotiations;
- Agreements/Contracts;
- Commitments;
- Fulfillment;
- financial obligations.

Phase 11 remains authoritative for materialized planning/Occurrences. Phase 13 remains authoritative for full Need/Offer matching. Phase 14 remains authoritative for negotiated obligations.

### Semantic summaries and provenance

Active Profile Need/Offer declarations may create coarse Actor Concept assertions:

- `needs`;
- `offers`.

Those assertions are only summaries for semantic reuse/discovery. The detailed `ActorProfileIntent` remains the source for quantity/time/route/lifecycle constraints.

The final hardening explicitly marks subsystem-managed summary assertions. A manual or independently-created Actor `needs`/`offers` assertion is never overwritten, reprivatized, or deleted when a Profile declaration is paused/closed.

### Authorization/privacy

- Profile owner authorization is rechecked at mutation boundaries;
- another Actor cannot mutate another Profile declaration;
- item visibility supports inherited/private/authenticated/public;
- item visibility can never bypass the parent Profile visibility;
- paused/closed declarations are not shown as active public Needs/Offers;
- public Profile rendering filters semantic assertions and intent declarations before presentation;
- Profile URLs/intent UUIDs grant no authority.

### UI

The Profile editor is intentionally split rather than becoming one giant form:

1. professional identity + profile media;
2. skills/interests/learning goals;
3. Needs & Offers.

4B now uses a progressive statement composer rather than an always-expanded schema form:

- minimal creation requires only relationship/type + Concept;
- optional facets are explicitly activated by the user;
- supported facets: title, description, quantity, location, route, timing and visibility;
- removing a facet clears its pending values;
- editing auto-activates only facets represented by the stored declaration;
- Concept inputs search reusable Platform and personal Actor Concepts;
- a user may keep typing and create a new personal reusable Concept when no suggestion fits;
- saved Need/Offer cards surface activated structured facets, including localized start/end date ranges and time windows.

This keeps simple declarations simple while preserving structured, future-matchable data underneath.

Public Profile cards render human-facing information instead of raw database primitives.

Localization is present in:

- English;
- Persian;
- Arabic;
- Simplified Chinese.

## 4B migration

Added:

- `2026_09_21_170000_create_actor_profile_intents_table.php`.

Important indexed dimensions include:

- Profile + kind + status;
- Concept + kind + status;
- visibility + status.

No Planner, matching, Proposal, Contract or accounting tables were introduced.

## 4B proof coverage

`tests/Feature/ActorProfileSemanticsAndIntentsTest.php` proves:

- one personal Concept reused across predicates;
- weekly transportation Need with route/time/next-day return;
- periodic quantity Need;
- recurring service Offer;
- active/paused/closed lifecycle;
- terminal closed history;
- recurrence validation;
- item-level public/authenticated/private visibility;
- public Profile filtering;
- cross-Actor mutation denial;
- coarse semantic Need/Offer synchronization;
- manual Concept assertions are preserved and never taken over by the Profile-intent synchronizer.

Existing `ActorProfileFoundationTest.php` continues to protect 4A identity/media/privacy behavior.

## Remote 4B validation

Final hardened runtime head:

`6dbc36c271f02f3851a7349171f77c9591d55ee6`

Passed:

- PHPUnit: **306 passed / 1570 assertions**;
- PHPStan: **no errors**;
- changed-file Pint: **113 files passed**;
- Vite production build;
- migration/scheduler/queue smoke;
- `actor_profile_intents` migration applied successfully in CI;
- SQLite backup → restore smoke;
- Composer security audit: no vulnerability advisories;
- JavaScript dependency audit/build gate.

## Defects found and resolved during 4B

1. Initial PHP formatting/static-analysis issues were corrected against the repository's pinned Pint/PHPStan rules.
2. Eloquent intent creation was changed to explicit typed relationship association.
3. Concept display-label fallback was made explicit and type-safe.
4. A provenance issue was found during final audit: Profile-intent synchronization could otherwise have modified/deleted an independent manual `needs`/`offers` assertion.
   - Fixed by marking only subsystem-created summaries with `actor_profile_intent_summary`.
   - Synchronization now leaves independent assertions untouched.
   - Regression test added.

## 4B.1 — temporal localization hardening

The human 4B browser smoke found a real MySQL defect while creating a weekly Transportation Need with optional date fields left empty:

~~~text
SQLSTATE[22007]: Invalid datetime format
Incorrect date value: '' for column 'starts_on'
~~~

The immediate cause was an empty browser string reaching a nullable DATE column. The broader finding was that locale, timezone and calendar behavior needed one explicit reusable contract before Planner and other time-sensitive domains build on Profile.

### Decisions and invariants

Language, timezone and calendar remain independent:

- locale controls UI language, direction and formatting;
- timezone controls the local civil clock and uses IANA timezone identifiers;
- timezone mode is either automatic/device-following or fixed;
- calendar controls input/display only;
- date-only domain values persist canonically as ISO/Gregorian `YYYY-MM-DD`;
- changing locale/calendar/timezone never rewrites stored dates;
- timezone must never be inferred from language.

Product defaults:

| Locale | Intl locale | Default calendar | First weekday |
| --- | --- | --- | --- |
| English | `en` | Gregorian | Sunday |
| Arabic | `ar` | Gregorian | Saturday |
| Simplified Chinese | `zh-CN` | Gregorian | Monday |
| Persian | `fa-IR` | Persian/Jalali | Saturday |

Calendar overrides:

- Gregorian;
- Persian/Jalali;
- Hijri/Umm al-Qura.

Arabic therefore does not automatically mean Hijri, and Chinese does not automatically mean the traditional lunisolar calendar. A user may choose a different supported presentation calendar explicitly.

### Implementation

- blank nullable temporal/quantity inputs normalize to `NULL` before validation/persistence;
- existing `users.timezone` is preserved;
- migration `2026_09_21_171000_add_temporal_preferences_to_users_table` adds:
  - `timezone_mode` (`auto` / `fixed`);
  - optional explicit `calendar` override;
- automatic timezone mode uses the browser/device IANA timezone;
- fixed timezone mode preserves the selected IANA zone;
- locale configuration now supplies Intl locale, calendar default and first weekday;
- Profile has a Date & time preferences section with live preview;
- recurring Need/Offer date inputs use a reusable calendar-aware picker;
- the picker renders Gregorian, Persian/Jalali or Hijri/Umm al-Qura while posting canonical ISO dates;
- public Profile dates/times are localized for the viewer;
- no third-party calendar dependency was added: the browser Intl engine is used;
- picker includes RTL-aware navigation, keyboard arrows, Escape, Today and Clear.

### Regression tests

`tests/Feature/TemporalLocalizationTest.php` proves:

- English/Arabic/Chinese default Gregorian presentation;
- Persian defaults Persian/Jalali;
- Persian uses Saturday-first weekday ordering;
- explicit calendar/timezone overrides remain independent of locale;
- the exact blank-date path persists `starts_on`, `ends_on`, time-window and optional numeric values as `NULL`;
- Persian Profile emits Persian-calendar picker metadata.

Existing User model schema/serialization tests were aligned because `timezone_mode` and `calendar` are now legitimate account-preference fields.

### Remote validation

Final hardened runtime head before this documentation update:

`b477c563abde4a307f8c41307cda3a02652e9735`

Passed:

- PHPUnit: **310 passed / 1593 assertions**;
- PHPStan: **no errors**;
- Pint: **125 changed PHP files passed**;
- Vite production build;
- fresh migrations including temporal preferences;
- fresh-install schema safety: `users.timezone` is added when missing, while rollback preserves any legacy/pre-existing timezone column;
- migration/scheduler/queue smoke;
- SQLite backup → restore smoke;
- Composer security audit: clean.

Because 4B.1 changes schema and visible date/time UX, the earlier local 4B gate must be refreshed once before final 4B acceptance.

## Owner-local/browser gate for 4B

Before 4C starts, synchronize the final 4B head and prove locally:

- migration applies;
- focused 4B tests pass;
- full suite/PHPStan/Pint/build pass;
- Profile editor shows Skills/Interests/Learning and Needs/Offers sections;
- one-time Need is easy to create;
- weekly transportation Need stores route, weekday/time and round-trip return;
- weekly quantity Need stores quantity/unit;
- recurring Offer stores cadence/time window;
- pause/reactivate/close behave correctly;
- closed declarations remain visible to the owner as history and cannot reactivate;
- public/authenticated/private item visibility behaves correctly;
- incognito public Profile never sees private/authenticated-only items;
- existing 4A profile/media behavior remains intact;
- existing Groups/Content/Invitation flows show no regression.

## Unresolved work intentionally deferred

4B does not implement:

- geographic coordinates/routing engine;
- occurrence materialization;
- reminders;
- automatic matching/ranking;
- proposals/negotiation;
- commitments/fulfillment;
- payment/accounting;
- selective context sharing grants;
- Profile completeness/requirements;
- organization/system Actor support.

These are roadmap work, not 4B defects.

## Next gate

4B plus 4B.1 temporal hardening is frozen for refreshed owner-local/browser acceptance.

After that succeeds, begin **4C — selective sharing, Profile completeness/requirements, privacy/accessibility polish, and final Phase 4 closure**. Do not jump to Planner or Need/Offer Matching merely because recurring declarations now exist.
