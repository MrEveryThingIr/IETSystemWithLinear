# Pre-Release Office Intent Registry — Closure Report

## Status

Remote technical implementation is complete.

- Branch: `feat/pre-release-office-intent-registry`
- Baseline: `cd04d576b3e426846c65d05c33f78e75a4034503`
- Runtime candidate: `cb4ae0783dc8f0d8ad424422b4cc7d6a9dc01520`
- GitHub Actions run: `35903601901`
- Intended first tag after owner acceptance: `v0.1.0-alpha.1`
- Phase 8: still closed

This report is the handoff for the last local/browser acceptance gate. Do not merge/tag a different SHA by accident.

## What this alpha proves

The release can replace a small office paper notebook with one coherent path:

```text
private Access Invitation
→ inspect welcome
→ register
→ verify email
→ Get Started
→ record Need / Offer
→ browse permission-aware Needs / Offers / Services
→ manually compare and introduce suitable cases
```

The product remains intentionally smaller than the full platform architecture.

## Implemented

### Standalone Access Invitation

New accounts no longer need a Group invitation as the forward product path.

Access Invitations provide:

- private hashed-at-rest tokens;
- optional reserved email;
- expiry and bounded use count;
- immutable acceptance evidence;
- platform-authorized issue/revoke UI;
- no-cache/no-referrer/noindex invitation responses;
- account creation + Actor identity;
- email verification;
- return to Get Started after verification.

Group Invitations are presented as collaboration/admission invitations for existing verified users. The historical Group-registration component/route remains only as a documented compatibility seam and is not linked by the new UI.

### Focused release experience

Default:

```text
IET_RELEASE_PROFILE=office_alpha
```

Ordinary navigation contains:

- Dashboard;
- Needs, offers & services;
- Profile;
- contextual Help.

Authorized platform administrators additionally see Access Invitations.

Advanced Group, Content, Actor-administration and development-audit navigation is hidden in the alpha without deleting the underlying kernels. `IET_RELEASE_PROFILE=full` restores the full navigation for development/regression use.

### Intent record

The release reuses `ActorProfileIntent`; there is no parallel marketplace table.

New queryable facets:

- Need / Offer;
- Property / Good / Service / Capital / Collaboration / Other;
- reusable Concept;
- ownership-transfer / temporary-use / service / financing / collaboration / other arrangement;
- location;
- optional cash range, currency and basis;
- non-binding value-exchange preference;
- optional negotiation notes;
- timing;
- visibility;
- existing active/paused/closed lifecycle.

### Value-exchange wording

The user chooses one of:

1. Cash-only arrangement.
2. Cash preferred, open to a structured mixed-value arrangement.
3. Open to a negotiated mixed-value arrangement.
4. Not decided — discuss when a suitable case appears.

A mixed-value preference may mention money plus clearly valued property/use rights, capital participation, or service/skill contributions.

It is deliberately **not** Contract/accounting truth. It creates no ownership share, capital right, debt, service obligation, payment, settlement or acceptance.

### Directory

The authenticated directory is read-only for other users.

Quick filters:

- All;
- Needs;
- Offers;
- Services;
- Property;
- Capital;
- Collaboration.

Additional free-text and location filtering is available.

An explicitly authenticated/public intent can be discoverable even when the owner's Profile remains private; in that case the intent appears but participant identity is not disclosed unless the Profile policy also permits it.

No Match row, score, recommendation, Proposal or Contract is created.

### AI boundary

The AI authoring architecture remains in source but is off by default:

```text
AI_ASSISTANCE_ENABLED=false
```

The alpha therefore does not expose a provider-dependent unfinished feature.

## Automated evidence

GitHub Actions run `35903601901` on exact runtime candidate `cb4ae0783dc8f0d8ad424422b4cc7d6a9dc01520`:

- PHPUnit: **419 passed / 2306 assertions**;
- PHPStan: **no errors**;
- changed-file Pint: **391 files passed**;
- Vite production build: passed;
- npm install/audit: **0 vulnerabilities**;
- migration status + rollback/reapply: passed;
- scheduler smoke: passed;
- database queue smoke: passed, no failed jobs;
- SQLite backup → restore smoke: passed;
- Composer security audit: no advisories.

Regression coverage includes standalone invited registration, reserved-email rollback, access-invitation authorization, Group-invitation existing-user behavior, guided intent creation, mixed-value semantics, privacy, Needs/Offers/Services filters, release-profile navigation, existing Actor administration under `full`, and the prior Phase 7 suite.

## Local synchronization gate

Use the existing database. **Do not run `migrate:fresh`.**

```bash
git fetch origin
git switch feat/pre-release-office-intent-registry
git pull --ff-only origin feat/pre-release-office-intent-registry
git status --short
git rev-parse HEAD

php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/AccessInvitationJourneyTest.php \
  tests/Feature/IntentDirectoryReleaseTest.php \
  tests/Feature/ReleaseExperienceTest.php \
  tests/Feature/InvitationJourneyTest.php \
  tests/Feature/Actors/ActorManagementTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
composer audit
```

Before migration, take the same kind of database backup you would require for deployment.

For the official manual source changes, after the application/database is synchronized and you are ready to publish a new manual edition:

```bash
php artisan system-manual:sync <owner-email>
```

The manual sync is explicit so ordinary bootstrap/seed behavior does not overwrite maintainer-edited official documentation.

## Local environment for the alpha

Recommended local/release values:

```text
APP_NAME=IET
IET_RELEASE_PROFILE=office_alpha
AI_ASSISTANCE_ENABLED=false
```

After changing `.env`:

```bash
php artisan optimize:clear
```

Use `IET_RELEASE_PROFILE=full` only when deliberately testing advanced kernels, then restore `office_alpha` before release acceptance.

## Browser acceptance

1. Open the root page and confirm it states registration is invitation-only.
2. As a Superadmin, open Access Invitations and create a one-use invitation reserved for a new test email.
3. Open the private link in an incognito/private browser.
4. Confirm the welcome page explains the product before registration and does not expose the full reserved email.
5. Register with the reserved email.
6. Using the local mail log, open the verification URL and confirm the user lands on Get Started.
7. Confirm ordinary alpha navigation shows Dashboard, Needs/Offers/Services and Profile; advanced Groups/Actors/Audit navigation is absent.
8. Create a Property Need to buy/acquire ownership with location, cash range and “cash preferred, open to a structured mixed-value arrangement”.
9. Confirm it appears under Needs and Property.
10. Create a Service Offer and confirm it appears under Offers and Services.
11. Create at least one Capital or Collaboration record and confirm its quick filter.
12. With a second verified user, confirm an authenticated intent is discoverable.
13. Keep the first user's Profile private and confirm the second user can see the shared intent without seeing the private participant identity.
14. Create a Private intent and confirm the second user cannot see it.
15. Confirm the value-exchange copy clearly says it is non-binding and creates no Contract/ownership/payment consequence.
16. Confirm a Group Invitation can be created for an existing verified user, while an unknown email is rejected and the Group invitation page does not offer new-account registration.
17. Confirm AI Content Assistant is not exposed while `AI_ASSISTANCE_ENABLED=false`.
18. Test mobile width and Persian/Arabic RTL layout for the new welcome, Get Started, wizard and directory surfaces. New alpha copy currently follows the English canonical catalog where reviewed localized copy has not yet been supplied.
19. Temporarily set `IET_RELEASE_PROFILE=full` only if needed to re-run the existing Phase 7 submit → reviewer queue/count → Evaluation browser regression; then restore `office_alpha`.

## Release freeze after acceptance

If all local/browser checks pass, freeze the exact accepted SHA. Recommended sequence:

1. record the accepted SHA and local validation in this report or a follow-up closure commit;
2. ensure CI is green on that final docs/closure head;
3. create `release/v0.1.0-alpha.1` from the accepted head;
4. create annotated tag `v0.1.0-alpha.1` on the same commit;
5. set deployed `APP_VERSION=v0.1.0-alpha.1` (or the exact tag-resolved SHA);
6. back up the target database before `php artisan migrate --force`;
7. deploy only the immutable tagged artifact;
8. execute the release smoke path from `docs/OPERATIONS_RUNBOOK.md`.

Do not open Phase 8 as part of this release freeze.

## Explicitly deferred

This alpha does **not** implement:

- automatic Matching/ranking/recommendations;
- Proposal/Negotiation workflow;
- Contract / Commitment / Fulfillment;
- ownership/equity/capital-right creation;
- Planner/Occurrences;
- invoices/payments/settlement/Accounting;
- automatic contact disclosure;
- generated media adapters;
- realtime infrastructure;
- Admission v2 Conversation;
- production provider selection.

Those remain later governed roadmap work.
