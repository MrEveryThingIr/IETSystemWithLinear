# Fresh-start browser acceptance journey

This guide is for a deliberate clean-room run after:

```bash
php artisan migrate:fresh --seed
```

The normal seed is intentionally not a demo database. It creates one verified
local super-admin and system-owned documentation only. Demo users, Businesses,
plans, deals, and Real Estate cases are not pre-created.

## Bootstrap identity

Default local credentials:

- username: `MrEveryThing`
- email: `mreverything@example.test`
- password: `password`

Change the placeholder email/password before or after the acceptance run. For a
fresh seed, override them with:

```dotenv
IET_BOOTSTRAP_SUPERADMIN_EMAIL=your-new-address@example.com
IET_BOOTSTRAP_SUPERADMIN_PASSWORD=choose-a-local-password
```

The bootstrap account is active, email-verified, owns an active Actor, and has
the Superadmin platform role.

## Journey 1 — build the first Business from zero

1. Log in as `MrEveryThing`.
2. Open Organizations → Businesses → Create business.
3. Create:
   - name: **مشاور املاک مهوری**
   - kind: **دفتر املاک / real_estate**
   - visibility: private
4. Open the Business and complete the internal profile/contact/location setup.
5. When the office is actually ready for visitors, change visibility to **public**.

Expected result:

- the Business is the canonical identity;
- a **کانال تخصصی املاک** section already exists;
- the specialized intake channel and private case-office are provisioned immediately;
- while the Business is private, its public website and guest intake URL return 404 and are not advertised;
- after the Business is published, its standalone public website exposes the Real Estate intake action;
- property catalog categories are provisioned automatically;
- Real Estate does not appear as a separate top-level application.

The public intake preserves the established Real Estate field contract:
intent, transaction type, contact, private/public location, property class and
subtype, land/construction dimensions, frontage, build year/calendar or
building age, condition, bedrooms, cabinet/ceiling/heating/cooling/floor/yard,
parking details, roof, toilet facilities, sale/deposit/rent pricing, images,
videos, recorded media, and notes.

### CLI compatibility

The historical `real-estate:intake-portal` command can no longer create a
parallel/orphan Real Estate system. If needed, it now requires an existing
Real Estate Business and only ensures that Business's canonical intake channel:

```bash
php artisan real-estate:intake-portal <business-uuid-or-code-or-slug>
```

## Journey 2 — a real-world seller does not need an account

After publishing the Business, open its standalone public website in a private/incognito browser and enter the Real Estate intake from there.

Scenario: **Ali**, a property owner, offers a residential property for sale.

Fill the form deeply, including dimensions, age/condition, facilities, parking,
price, notes, and media. Submit it.

Expected result:

- the public visitor sees only their one-time preview;
- exact address/contact data are not exposed publicly;
- the Business office receives a private case;
- Ali becomes a Business contact/client without requiring an IET account;
- an authorized office operator can qualify the case;
- a qualified offer can be promoted to a versioned **property Listing** in the
  Business catalog without destroying the original intake case.

## Journey 3 — invite actual platform users

As Superadmin open **Admin → Access invitations**.

Create separate single-use invitations for several people. Suggested actors:

- `SaraBuyer` — a buyer/customer;
- `RezaAgent` — an agent who will work in مشاور املاک مهوری;
- `NimaWorker` — a service worker for a later paid-work scenario;
- `MaryamOwner` — another Business owner.

Use different browser profiles/incognito sessions to redeem each invitation,
register, and verify the accounts. This intentionally exercises the real access
journey rather than inserting users through seeders.

After registration, publish only the feature surfaces each person actually
needs. Publication and record/domain authorization remain separate.

## Journey 4 — Business team collaboration

Add `RezaAgent` to **مشاور املاک مهوری** as a manager/member.

Verify from Reza's session:

- the Business is visible only after Business publication;
- the Real Estate case office appears *inside that Business workflow*;
- Reza can see/manage only what their Business role permits;
- client/contact, catalog, property cases and Business context are coherent
  rather than duplicated systems.

Have Reza review Ali's case and promote the qualified property offer to the
catalog.

## Journey 5 — buyer Need → market → relationship/deal

Using `SaraBuyer`:

1. create a Need for a property matching Ali's listing;
2. discover/match the Business offer;
3. start the supported relationship/Deal flow;
4. exchange proposal/terms;
5. form a Contract when both sides agree.

The acceptance question is not only whether each page works, but whether every
transition makes it obvious **who is doing what, with whom, about which Need,
Offer, Listing, Business and Context**.

## Journey 6 — execution and money

Use a simple paid-work Contract between two invited accounts, for example
`MaryamOwner` hires `NimaWorker` for selected workdays.

Exercise the complete chain:

```
Contract
  → Commitment
  → Planner occurrences
  → start/complete work
  → Fulfillment
  → explicit review/acceptance
  → Financial Obligation
  → Settlement claim
  → counterparty confirmation
  → each party's Accounting
```

Check temporal preferences, readiness windows, recurring Planner density,
accepted-work derivation, outstanding/paid amounts, and settlement timestamps.

## Journey 7 — repeat with another Business type

Create a normal service or retail Business and repeat client/catalog/market
flows. This verifies that Real Estate is a specialization of the Business
foundation, not the architecture that all other Businesses are forced to copy.

## What to record while testing

For every confusing step, capture:

- current URL/page;
- logged-in user;
- intended action;
- what the UI made you think would happen;
- what actually happened;
- whether the missing concept is publication, authorization, domain ownership,
  navigation, terminology, or workflow state.

That evidence should drive the next coherence/redesign milestone.


## Public Business website rule

Every active public Business has a first-class standalone public website at
`/b/{business-slug}`. This is not a public version of the internal Business
workspace. The visitor should experience the Business as the website itself:

- no Everything application navigation, Planner, Money, Deals, Businesses, or
  administration chrome;
- public identity, public contact/location, published listings, and only the
  Business-owned public actions that are actually available;
- Real Estate intake uses the Business-scoped
  `/b/{business-slug}/property-intake` path from the public website;
- the historical token-based `/office/{token}` intake remains compatible for
  existing QR codes/links, but it is no longer the canonical link advertised by
  the Business website;
- an owner/manager can optionally select other active public Businesses to
  advertise. Nothing is cross-promoted by default.

Inside the authenticated Business workspace, every public Business exposes a
prominent **Public website** action. A private/paused Business does not render a
dead public link; managers instead see publication guidance.

Superadmin has domain-wide Business visibility/management without needing to be
added as a member of every Business. Ordinary accounts still require both the
published Business feature surface and the appropriate Business membership/role.
