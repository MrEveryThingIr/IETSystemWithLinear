# Pre-Release Office Intent Registry

## Status

Explicitly approved pre-release productization slice before Phase 8.

- Branch: `feat/pre-release-office-intent-registry`
- Baseline: `cd04d576b3e426846c65d05c33f78e75a4034503`
- Intended release family: `v0.1.0-alpha.1`
- Release meaning: invitation-only development alpha, not Phase 20 production

## Business proof

A small real-estate / housing / construction office must be able to replace the daily paper notebook used to record:

- a person who has property or another thing to sell or make available;
- a person who needs property to buy or rent;
- a person who offers or needs a service/skill;
- a person who offers or needs capital/financing;
- a person seeking collaboration around a project.

The system must make those records easy to enter, read and filter without pretending that discovery already constitutes a Match, negotiation, Contract, ownership transfer or payment.

## Core flow

```text
private Access Invitation
→ invitation-only welcome / inspect
→ register
→ verify email
→ Get Started
→ Record a Need or Offer
→ active ActorProfileIntent
→ permission-aware read-only directory
→ manual human comparison / introduction
```

A Group Invitation is a separate concern and is presented as an invitation for an already registered, verified user to begin a Group Admission journey.

## Canonical record

The office registry does not introduce a second marketplace truth.

`ActorProfileIntent` remains the canonical current-intent record. The pre-release slice extends it with queryable discovery facets:

- Need or Offer;
- broad subject kind: Property, Good, Service, Capital, Collaboration, Other;
- reusable Concept describing the actual subject;
- arrangement kind: ownership transfer, temporary use, service, financing, collaboration, other;
- optional location;
- optional cash range + ISO-style three-letter currency + basis;
- value-exchange preference;
- optional negotiation notes;
- timing;
- visibility and lifecycle.

## Value-exchange preference

The wizard asks a non-binding question about what forms of consideration the person is currently open to discussing:

1. Cash only.
2. Cash preferred, open to a structured mixed-value arrangement.
3. Open to a negotiated mixed-value arrangement.
4. Not decided; discuss later.

A mixed-value arrangement may be described in plain language as money combined with other contributions that would later need explicit valuation and agreement, for example:

- property or temporary-use rights;
- capital participation;
- services or skills contributed as value.

These fields are discovery/negotiation preferences only. They never create:

- ownership or equity;
- capital rights;
- debt;
- service obligations;
- Contract acceptance;
- payment;
- settlement;
- accounting entries.

Those facts belong to later explicit Proposal/Negotiation/Contract/Commitment/Accounting domains.

## Privacy

- Private intent: owner only.
- Authenticated intent: active verified users may discover the intent.
- Public intent: may be discoverable publicly when a public surface is intentionally provided.
- Inherited intent: follows Profile visibility.

Explicit intent sharing does not automatically expose an otherwise-private Profile. The directory shows participant identity only when the Profile itself is viewable by that viewer.

## Directory

The first release is deliberately read-only for other users.

Quick filters:

- All;
- Needs;
- Offers;
- Services;
- Property;
- Capital;
- Collaboration.

Additional filters include free-text subject/description and location. The first release bounds the result set instead of loading an unbounded table.

No Match row or score is created. Human staff/users manually compare records and introduce suitable cases.

## Release experience profile

The first published alpha defaults to:

```text
IET_RELEASE_PROFILE=office_alpha
```

In `office_alpha`:

- ordinary navigation exposes Dashboard, Needs/Offers/Services, and Profile;
- contextual Help remains available from the header;
- authorized platform administrators still see Access Invitations;
- Groups, advanced Content entry points, Actor administration and development-audit navigation are hidden from the ordinary release surface;
- the underlying routes/kernels are preserved so development can continue without destructive feature removal.

Set `IET_RELEASE_PROFILE=full` only when deliberately testing or operating the full platform experience.

## AI

The AI authoring seam remains in source but is disabled by default:

```text
AI_ASSISTANCE_ENABLED=false
```

The release must not show a broken AI feature merely because no provider is configured.

## Acceptance

Before release integration/tagging:

- forward-migrate an existing database; no `migrate:fresh`;
- run focused onboarding/intent tests;
- run complete PHPUnit/PHPStan/Pint/Vite;
- confirm rollback/reapply of the release migrations;
- confirm queue/scheduler and backup/restore gates;
- browser-test standalone invite → register → verify → intent;
- browser-test Need/Offer/Service/Property/Capital/Collaboration filters;
- test private/authenticated visibility with two users;
- test Group Invitation with an existing verified user;
- confirm disabled AI is absent/inaccessible;
- test mobile width and Persian/Arabic RTL layout;
- reconfirm the existing Phase 7 submission/reviewer/evaluation regression.

No release tag is created until the owner accepts the synchronized local/browser candidate.
