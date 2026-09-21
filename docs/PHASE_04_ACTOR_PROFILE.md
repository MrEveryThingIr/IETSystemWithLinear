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

### 4B — semantic Profile + recurring Needs/Offers — active

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
