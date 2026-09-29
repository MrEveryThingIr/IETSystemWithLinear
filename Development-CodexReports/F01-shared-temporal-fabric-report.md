# F1 — Shared Temporal Fabric Recovery and Second Review

## Status

**Remote implementation gate complete. Owner browser acceptance pending.**

Exact remotely-green review head:

~~~text
branch: codex/review-f1-shared-temporal-fabric
SHA: 0fe4eda4ce945aaed36f3a8a6e20e3aa87850a95
CI: 36244467404
result: success
~~~

PR: #36 — F1: recover shared temporal fabric.

## Objective

Restore the already-developed, previously browser-familiar temporal experience early in the selective assembly, while revising its boundaries to match the capability-mesh architecture.

F1 is cross-cutting infrastructure. It must not make Planner, Finance, Contract, Agreement or any other business node the owner of time presentation.

## Trigger

M00 browser inspection confirmed that the selective assembly was a healthy but older product baseline. Important later work was missing:

- profile-aware calendar/date/time rendering throughout the system;
- Persian/Jalali and supported Hijri presentation;
- Gregorian equivalence for non-Gregorian primary presentation;
- timezone-aware instant rendering;
- date-only values protected from timezone day shifting;
- profile-aware date/datetime inputs;
- permanent live date/time in the app shell;
- rotating ambient status text;
- fractal calendar drill-down to minute precision.

## Source inventory

Primary source library:

~~~text
codex/release-first-publication-hardening
latest inspected source: 5830cb44e09c0238f1f1591f0134419ba67834c3
~~~

Focused related source lines:

~~~text
integration/ideal-v1-planner-temporal-candidate
integration/ideal-v1-temporal-calendar-reconcile
fix/planner-execution-window-calendar-evidence
fix/planner-temporal-evidence-calendar-hardening
~~~

The recovery deliberately did **not** wholesale-merge any source branch.

## Important source-boundary decision

The publication-hardening source mixed default monetary-unit provisioning into the Profile Temporal Preferences component.

That coupling is rejected for F1.

Temporal Preferences owns:

- timezone;
- timezone mode;
- calendar system;
- temporal preview.

Finance/Profile capability will own default MonetaryUnit preference in its own node review.

Therefore F1 recovers temporal presentation without importing:

- ProvisionVerifiedUserDefaults into Temporal Preferences;
- default_monetary_unit_code persistence;
- automatic personal ledger provisioning;
- finance-specific profile controls.

Translation keys that may already exist are harmless, but no F1 runtime behavior depends on them.

## Recovered implementation

The first atomic recovery transplanted 70 selected source blobs onto the current assembly instead of replaying the whole source branch.

Recovered concerns include:

- PHP intl requirement in CI;
- TemporalCalendar profile-aware arithmetic/formatting;
- local-date, local-datetime and local-time components;
- profile-aware date and datetime inputs;
- JS temporal/date-picker/datetime-picker support;
- Gregorian equivalent rendering;
- application-wide replacement of direct Gregorian presentation in relevant views;
- Home/Today temporal projection fixes;
- permanent ambient-status shell component;
- ambient live clock and rotating messages;
- Planner profile-calendar navigation;
- year → month → day → hour drill-down;
- 60 / 30 / 15 / 5 / 1 minute partitions;
- exact Planner create prefill from selected calendar slots;
- TemporalPresentationConsistencyTest.

A separate small correction adds the Gregorian-equivalent preview to the existing Temporal Preferences view **without** importing its source branch's currency UI.

## Authority map

### Temporal Kernel owns

- interpretation/presentation helpers for user temporal preferences;
- profile timezone resolution;
- profile calendar resolution;
- locale/calendar formatting;
- temporal input/display primitives;
- calendar arithmetic helpers.

### Temporal Kernel does not own

- Plan/Occurrence state;
- Contract effective rules;
- Agreement lifecycle;
- Financial obligation due state;
- Content publication lifecycle;
- Submission deadlines;
- Reminder domain truth.

### Ambient rail owns

No domain truth.

It is a projection surface.

### Fractal Calendar owns

No business event truth.

It is temporal navigation + authorized projection + action entry.

## Current implementation caveat

The recovered fractal calendar is still rendered through the Planner index because that is where the proven implementation currently lives.

This is accepted only as a **recovery state**, not the final conceptual ownership boundary.

Second-review target:

~~~text
shared Calendar surface
→ registered authorized temporal projection providers
→ source-domain links/actions
~~~

Planner becomes the first provider, not the owner of the Calendar concept.

Do not duplicate domain records into a calendar-events truth table.

## Capability-mesh target

The eventual provider contract should allow independently accepted nodes to contribute temporal items, for example:

- Planner occurrences/reminders;
- Contract effective/review/expiry dates;
- Group Agreement activation/effective dates;
- Financial obligation due dates;
- Settlement/payment events;
- Admission/review windows;
- Content publication/review times;
- Submission/Evaluation due times;
- explicit notes/annotations with temporal placement.

Each projection must include:

- provider/source identity;
- stable source reference;
- start/end or date semantics;
- viewer authorization;
- label/summary;
- source URL/action;
- no duplicate authority.

## Ambient rail second-review target

Initial recovered rail:

- live profile-aware date/time;
- Gregorian equivalent under non-Gregorian calendar;
- timezone-aware clock;
- rotating ambient messages.

Future providers may optionally expose compact authorized projections such as:

- current/next Plan;
- reminder;
- waiting-on-me;
- unread notifications;
- due financial item.

Rendering the rail must never mutate source-domain state.

## Validation requirements

Focused:

~~~bash
php artisan test --compact tests/Feature/TemporalPresentationConsistencyTest.php
php artisan test --compact --filter=Planner
~~~

Full:

~~~bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan view:cache
php artisan view:clear
php artisan test --compact
npm run build
composer audit --locked --no-interaction
npm audit --audit-level=high
~~~

CI must also prove MySQL migration portability, SQLite operation/restore smoke and PHP intl availability.

## Browser acceptance

Use exact F1 review head.

### Profile preferences

1. Set English + Gregorian + Toronto timezone.
2. Verify preview/calendar/input/output coherence.
3. Set Persian + Persian calendar + Tehran timezone.
4. Verify Jalali primary rendering.
5. Verify Gregorian equivalent appears under/alongside the primary date/date-time where designed.
6. Set Arabic + supported Hijri calendar and verify RTL/date presentation.
7. Set Simplified Chinese + Gregorian and verify localization.
8. Switch timezone and confirm true instants change appropriately while civil date-only values do not shift a day.

### Cross-system temporal presentation

Inspect representative surfaces:

- Home/Today;
- Planner list/detail/create/calendar;
- Accounting;
- Contract;
- Commitment/Fulfillment;
- Financial Obligation;
- Group Agreement/invitation;
- Admissions;
- Content Reader/Studio;
- Conversation/Timeline;
- Submission review;
- Notifications;
- Actor/profile sharing.

No raw hard-coded Gregorian display should bypass the profile temporal layer.

### Ambient header

Verify on multiple authenticated views:

- live clock continuously updates;
- selected calendar/timezone is honored;
- Gregorian equivalent is visible for non-Gregorian primary calendar;
- ambient text rotates;
- layout remains usable in LTR/RTL and representative desktop widths.

### Fractal calendar

Verify:

~~~text
year
→ month
→ day
→ hour
→ 60 / 30 / 15 / 5 / 1 minute slots
~~~

Create a Plan from a selected slot and verify exact date/time/duration prefill.

## Exit criteria

F1A/F1B can be accepted when profile-aware temporal behavior and ambient shell behavior pass remote + owner browser validation.

F1C is fully closed only when the recovered Calendar has a documented/implemented shared projection boundary so later nodes can plug in without Planner ownership or duplicated truth.

F1D Capability Launcher remains a separate composition-contract step and must not be faked through hidden cross-domain writes.


## Remote validation result

The selective transplant exposed and corrected two compatibility classes before acceptance:

1. copying whole older `ui.php` locale files temporarily erased newer assembly access/group translation keys;
2. one recovered Commitment view still contained literal `Daily`.

Corrections preserved the newer assembly locale files, merged only required temporal/group labels, and localized the literal schedule label.

Final exact-head CI `36244467404` passed:

- MySQL migration portability;
- npm audit/build;
- changed-PHP Pint verification;
- PHPStan;
- scheduler/queue/rollback smoke;
- SQLite backup/restore smoke;
- complete PHPUnit suite;
- Composer security audit.

The branch remains unmerged until owner browser acceptance because F1 changes visible cross-system date/time/calendar behavior.
