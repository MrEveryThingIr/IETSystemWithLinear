# Phase 4 — Actor Profile Implementation Report

## Status

**Phase 4 runtime is complete and remote-validated; final owner-local/browser acceptance is pending.**

- 4A — professional identity + profile media: complete.
- 4B — semantic Profile + recurring Needs/Offers: complete.
- 4B.1 — temporal localization hardening: complete.
- 4C — purpose-specific completeness + selective disclosure: complete.
- final cross-system Profile/identity/scoring hardening: complete.

Final runtime baseline before this documentation-only closure:

`20e7c2834fca74b652f89195094b70f86b454f80`

Final GitHub Actions run: `35700986122` — **success**.

Phase 5 remains blocked until the human owner completes the final local sync/test/browser acceptance gate.

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

4B.1's schema and visible date/time UX were refreshed owner-locally on `ea52eef`; the gate is closed.

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

## 4C — selective sharing, completeness and future-safe integration

Implemented from the accepted 4B/4B.1 baseline `ea52eef`.

### Purpose-specific Profile requirements

4C adds explicit Profile requirement descriptors and a completeness service rather than embedding one global completeness percentage into Actor/Profile state.

Supported requirement kinds:

- Profile field;
- Concept assertion predicate;
- active Need/Offer intent.

The caller supplies the requirement set. The owner UI shows one recommended readiness set, but an Admission/Context in later phases can supply a different set without mutating global Profile requirements.

### Selective disclosure

Added:

- `ActorProfileDisclosureGrant`;
- `ActorProfileDisclosureItem`;
- `CreateProfileDisclosureGrant`;
- `RevokeProfileDisclosureGrant`;
- `ActorProfileDisclosureGrantPolicy`;
- `ProfileDisclosureCatalog`;
- `ProfileDisclosureResolver`;
- owner-side `Profile\\Sharing` Livewire component;
- recipient-side `Profile\\SharedShow` Livewire component;
- disclosure migration and factories;
- multilingual UI strings.

A grant records an immutable selection of shareable Profile field keys, Actor Concept-assertion UUIDs and active Profile-intent UUIDs for one explicit grantee Actor.

Grant terms include:

- purpose;
- optional expiry;
- revocation timestamp;
- creator/Profile owner provenance.

### Privacy/authorization hardening

4C explicitly proves and enforces:

- account email cannot be selected;
- unselected display name/username/biography cannot leak from recipient-view chrome;
- foreign Profile items cannot be inserted into another owner's grant;
- an arbitrary disclosure URL/UUID provides no authority;
- only the selected active Actor backed by an active verified User can view;
- inactive/archived recipient Actors lose access;
- owner provenance is validated;
- self-grants are rejected;
- expiry/revocation deny future reads immediately.

A privacy review during implementation caught and fixed an early shared-page heading that could have exposed an unselected owner display name/username. Regression coverage now protects that boundary.

### Live-access semantics

The grant is intentionally a live disclosure permission, not a historical snapshot.

- current selected Profile field values are resolved at read time;
- assertions must still be temporally valid;
- Profile intents must still be active;
- closing a shared intent makes it disappear from the grant view.

This is deliberately different from future Contract evidence. Phase 14 must capture immutable/versioned contractual evidence independently.

### Integration boundaries preserved

No Phase 5/8/11/13/14 state was pulled forward.

- no generic Context model/FK/authorization was added;
- no Admission v2 requirements were implemented;
- no Planner occurrences/reminders were generated;
- no matching engine or Match records were created;
- no Proposal/Negotiation/Contract/Commitment/Fulfillment state was created.

The intended dependency remains:

~~~text
Actor / Profile / Concepts / temporal preferences
        ↓
Context + Admission consumers
Planner consumers
Matching consumers
Negotiation/Contract consumers
~~~

Downstream domains may consume Profile services and explicit disclosure; they must not treat mutable Profile rows as their own authoritative historical state.

### Bounded reads / product surface

- owner UI shows at most 20 recent grants;
- a grant accepts at most 100 selected items;
- shareable semantic assertions are bounded to 100;
- shareable active Profile intents are bounded to 100;
- English/Persian/Arabic/Simplified Chinese strings are present;
- existing responsive/RTL-aware application components are used.

### 4C proof and final Phase 4 hardening

Final runtime baseline:

`20e7c2834fca74b652f89195094b70f86b454f80`

GitHub Actions run `35700986122` proves the exact runtime commit:

- PHPUnit: **331 passed / 1719 assertions**;
- PHPStan: **no errors**;
- Pint changed-file gate: **171 files passed**;
- Vite production build: passed;
- fresh migrations: passed, including `2026_09_22_080000_add_importance_percent_to_actor_profile_intents.php`;
- migration / scheduler / database queue smoke: passed;
- SQLite backup → restore smoke: passed;
- npm high-severity audit: passed;
- Composer security audit: clean.

The final owner review also required system-wide integration polish rather than treating Profile as an isolated page. The accepted implementation therefore includes:

- read-first/on-demand Profile editors while leaving existing Profile data and cards visible;
- restoration of the useful two-column Profile layout with separate displayed-image and always-visible image-library cards;
- reusable displayed-image avatar behavior;
- reusable avatar + participant identity links across system surfaces that reference Actors;
- human-facing displayed name in greetings and account UI;
- participant-reference URLs that are side-effect free and do not create another user's Profile;
- authenticated identity-only reference protection while preserving genuinely public guest Profile access;
- archived-Actor avatar denial;
- optional skill self-rating from **0–100%**, mapped to the Concept Assertion `weight` dimension;
- optional Need/Offer **importance / urgency from 0–100%**, stored as `importance_percent`;
- localized qualitative bands for those percentages;
- score rendering in ordinary Profile presentation and selective disclosure only when the underlying item is visible/selected;
- four-locale UI coverage;
- regression coverage for persistence, update, clearing, bounds, public rendering and progressive composer behavior.

Important interpretation:

- skill proficiency is a self-described Profile statement, not an objective certification;
- intent importance/urgency is a participant declaration, not Planner priority, matching rank or contractual obligation;
- mutable Profile scores must be snapshotted/version-bound later if a Contract needs them as historical evidence.

## Defects found in the final eagle-eye audit

The closure audit found and corrected several edge issues before acceptance:

1. one earlier UI pass over-collapsed the Profile page instead of collapsing only mutation forms;
2. the displayed-image and full image-library surfaces needed to remain distinct and visible;
3. an interrupted patch had malformed Blade references to `ProfileScale`;
4. participant Profile references initially risked creating another Actor's Profile as a read side effect;
5. identity-only participant reference needed authentication to avoid becoming a guest username-enumeration surface;
6. archived Actors required explicit avatar denial;
7. Pint caught two mechanical test-format/import issues;
8. canonical `CURRENT_STATE` and roadmap/report documents were stale and could have caused a later agent to reimplement already-complete Concept/Profile work.

All of the above are resolved in the closed milestone.

## Phase 4 closure

Final Phase 4 human acceptance is **pending**. Remote implementation and CI are complete.

Closed architectural invariants:

~~~text
User != Actor
ActorProfile = mutable participant presentation/profile state
Concept = semantic identity
Profile intent != Planner Occurrence
Profile Need/Offer != Match
Profile disclosure != Context authorization
Profile disclosure != Contract evidence
mutable current Profile != immutable historical evidence
~~~

No Phase 5 Context model, Phase 11 Planner occurrence, Phase 13 Match, or Phase 14 Contract/Commitment state was pulled forward.

The repository is technically ready for **Phase 5 — Generic Content Context**, but Phase 5 must not begin until the owner-local/browser gate passes.
