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

### 4A — professional identity + profile media foundation

Implemented remotely; owner-local gate pending.

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

### 4B — structured facts + Concept integration

Next after 4A owner acceptance.

Planned:

- Profile fact model for non-semantic structured attributes that do not belong in User;
- Concept-backed skills;
- Concept-backed interests;
- Concept-backed learning goals;
- Concept-backed needs/offers where appropriate to Profile scope;
- per-fact/assertion visibility;
- professional Profile sections;
- edit/search/select UX backed by Phase 3 Concept identity;
- no duplicate skill/category tables.

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
