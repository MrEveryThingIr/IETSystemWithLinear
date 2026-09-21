# Phase 4 — Actor Profile Implementation Report

## Status

Phase 4 is active on `feat/phase-04-actor-profile`.

- 4A — professional identity + profile media: **implemented and remote-CI validated; owner-local validation pending**.
- 4B — structured facts + Concept integration: **not started**.
- 4C — selective sharing/completeness/final closure: **not started**.

## Starting point

- accepted Phase 3 closure: `b2e5dc0a8b7cfd33ff9dbcb6af4c6f6027c7948c`;
- Phase 4 branch: `feat/phase-04-actor-profile`;
- contract: `docs/PHASE_04_ACTOR_PROFILE.md`.

## 4A implementation

### Domain

Added:

- `ProfileVisibility`;
- `ActorProfile`;
- `ActorProfileImage`;
- Actor→Profile relation;
- Asset→ProfileImage relation.

Profile identity/provenance cannot be reassigned through ordinary updates.

### Profile fields

- display name;
- professional headline;
- biography;
- location text;
- website URL;
- profile visibility;
- selected display image.

Account email remains User data and is intentionally absent from public Profile output.

### Media

The existing Asset pipeline was generalized narrowly so a private Asset may exist without GroupSpace ownership when its purpose is an Actor Profile image.

Profile upload contract:

- private local storage;
- immutable Asset UUID/storage/hash provenance;
- JPEG/PNG/WebP/AVIF;
- 8 MB maximum;
- minimum 128×128;
- maximum 8000×8000 and 40 megapixels;
- existing scan/processing job outside local/testing;
- owned-rights status;
- dimensions/purpose captured in metadata;
- maximum 12 images per Profile.

The first image becomes the selected display image. Any ready library image may later replace it. Selection may be cleared.

### Authorization/privacy

`ActorProfilePolicy` proves:

- archived Actor profiles are not visible;
- owner always sees own active Profile;
- private hides from other users and guests;
- authenticated requires active verified account;
- public allows guest viewing;
- only owner may update.

Profile image delivery performs the Profile visibility check again and requires a ready image Asset.

### UI

Added:

- signed-in `/profile` editor;
- public/controlled `/profiles/{public_uuid}` view;
- profile-image endpoint;
- Profile navigation in sidebar and account menu;
- professional header/avatar/profile card;
- identity editor;
- image library;
- display-image selection;
- responsive layout;
- English, Persian, Arabic and Simplified Chinese localization.

### Migrations

- make `assets.group_space_id` nullable for profile-owned private Assets;
- create `actor_profiles`;
- create `actor_profile_images`;
- add selected display-image FK;
- rollback cleans Profile-only Asset rows before restoring the old Asset constraint;
- rollback table drops are SQLite-safe.

## Defects found and resolved during remote validation

1. Profile creation initially used mass assignment for protected `actor_id`.
   - Fixed by creating the Profile through the locked Actor relationship.

2. Initial rollback dropped a named FK directly.
   - SQLite cannot do that.
   - Fixed by dropping the paired Profile tables inside `withoutForeignKeyConstraints`.

3. PHPStan found several precise typing issues.
   - redundant nullable/array guards removed;
   - image dimensions use the proven `getimagesize` shape;
   - Eloquent Profile fields assigned explicitly;
   - Asset Profile relation correctly typed.

## Remote validation

Runtime head `0a3aea2f0f2f7cbaa1d81c5dd47c6a6474a5fc81` passed:

- Vite production build;
- changed-file Pint: **91 files passed**;
- PHPStan: **no errors**;
- migration/scheduler/queue smoke;
- SQLite backup→restore smoke;
- PHPUnit: **298 passed / 1528 assertions**;
- Composer security audit: no vulnerability advisories;
- npm audit/build gate.

## Focused 4A proof

`tests/Feature/ActorProfileFoundationTest.php` covers:

- one default-private Profile per Actor;
- public UUID route;
- private/authenticated/public visibility;
- no public email leakage;
- cross-Actor update denial;
- image upload through private Asset pipeline;
- authorized image delivery;
- display image switch/clear;
- Asset/storage cleanup after image removal;
- non-image rejection.

## Browser status

Not yet owner-validated.

Expected after local synchronization:

- Profile appears in sidebar and account menu;
- first visit creates a private Profile;
- identity fields save;
- Preview obeys visibility;
- image upload/gallery works;
- first image becomes avatar;
- another ready image can become avatar;
- avatar can be cleared/removed;
- public Profile is viewable in incognito only when visibility is Public;
- email never appears on public Profile;
- existing Group/Content/Invitation screens remain unchanged.

## Next gate

Owner synchronizes this 4A milestone, runs focused/full/static/format/build/migration checks, performs the browser checklist, and reports the result. Only then does 4B begin.
