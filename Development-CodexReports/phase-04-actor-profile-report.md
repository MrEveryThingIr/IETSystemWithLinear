# Phase 4 — Actor Profile Implementation Report

## Status

Phase 4 is active on `feat/phase-04-actor-profile`.

- 4A — professional identity + profile media: **complete and owner-local accepted**.
- 4B — semantic Profile + recurring Needs/Offers: **implementation complete and remote-CI validated; owner-local/browser acceptance pending**.
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

The Need/Offer editor keeps simple declarations simple and exposes schedule/route recurrence fields only when useful.

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

4B implementation is frozen for owner-local/browser acceptance.

After that succeeds, begin **4C — selective sharing, Profile completeness/requirements, privacy/accessibility polish, and final Phase 4 closure**. Do not jump to Planner or Need/Offer Matching merely because recurring declarations now exist.
