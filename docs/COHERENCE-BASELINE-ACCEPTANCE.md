# Coherence Baseline Acceptance — C5 to C7

This document is the browser-testable baseline after the coherence milestones.

It exists because passing domain tests is not enough: the user must be able to see
Business → market → Deal/Contract → work → IET economics as one story.

## Load the local demo world

This seeder is intentionally restricted to the `local` and `testing` environments.

```bash
php artisan db:seed --class=CoherenceBaselineDemoSeeder
```

A normal local `php artisan db:seed` also loads this demo world.

The seeder is idempotent and may be run again.

Primary demo login:

- email: `test@example.com`
- username: `testuser`
- password: `password`

Buyer accounts use password `password`:

- `inspection-buyer@example.com`
- `tool-buyer@example.com`
- `home-buyer@example.com`
- `property-buyer@example.com`

## Businesses visible to testuser

After seeding, Organizations → Businesses must show all four:

1. **Safdar Real Estate Office** — Real Estate is a Business vertical, not a top-level system.
2. **Atlas Inspection Services** — professional service.
3. **Everyday Tools Store** — goods / retail.
4. **Bright Home Services** — home service.

Each Business uses the same Business foundation: clients, catalog, market publication,
Deals/Contracts and Money projection. Real Estate adds specialized property intake and
property fields inside that Business.

## Seeded economic scenarios

| Scenario | Receiver | Provider | Value | Seeded state |
| --- | --- | --- | ---: | --- |
| Dimensional inspection | inspection-buyer | testuser / Atlas | 120 IET | deposited and fully settled |
| Cordless drill deliverable | tool-buyer | testuser / Everyday Tools | 80 IET | accepted, outstanding |
| AC maintenance | home-buyer | testuser / Bright Home | 150 IET | accepted, outstanding |
| Property brokerage service | property-buyer | testuser / Safdar Real Estate | 200 IET | accepted, outstanding |

Expected IET position after the seed:

- `testuser`: wallet **120**, receivable **430**, payable **0**, net position **+550 IET**,
  immediately cash-out eligible **120 IET**;
- inspection buyer: **0 IET net** after placeholder deposit and IET settlement;
- tool buyer: **-80 IET net**;
- home buyer: **-150 IET net**;
- property buyer: **-200 IET net**.

The outstanding buyers demonstrate debt. Every seeded scenario also has a canonical
Need + Business Offer + accepted Relationship/Deal + Deal-sourced Contract before
fulfillment creates the IET obligation. The settled inspection scenario additionally
demonstrates the complete deposit → internal IET settlement → provider funded-wallet path.

Business projections after seeding include:

- Atlas Inspection Services: one Deal, one Contract, 0 outstanding receivable,
  **120 IET realized revenue/profit**;
- Everyday Tools Store: one Deal, one Contract, **80 IET outstanding receivable**;
- Bright Home Services: one Deal, one Contract, **150 IET outstanding receivable**;
- Safdar Real Estate Office: one seeded brokerage Deal/Contract with
  **200 IET outstanding receivable**.

## What “IET balance” means in this baseline

Do not create a mutable negative-wallet shortcut.

The human-facing economic position is derived from authoritative facts:

```text
IET net position
= funded IET wallet
+ outstanding accepted IET receivables
- outstanding accepted IET payables
```

Therefore an accepted 100 IET service produces immediately:

- receiver: 0 wallet + 0 receivable − 100 payable = **−100 IET**;
- provider: 0 wallet + 100 receivable − 0 payable = **+100 IET**.

If that receiver later provides accepted work worth 150 IET, the position becomes:

```text
0 wallet + 150 receivable - 100 payable = +50 IET
```

This is the intended “work your way out of debt” behavior. The immutable obligations
remain auditable even though the human economic position has become positive.

### Internal spending versus external cash-out

A positive net position represents internal economic capacity: the user may continue
to enter Needs and agreements, and new accepted obligations change the same net
position.

External cash-out is deliberately stricter. Only IET that has reached the funded
wallet through confirmed settlement is immediately cash-out eligible. The Exchange
cash-out is still a placeholder request, and confirmation is also protected by the
platform treasury reserve.

This distinction prevents an unsettled receivable from silently becoming external
money while still allowing the platform to show the user's true internal position.

## Business → market handoff

A published Business catalog version can be sent to **Needs & Offers**.

The resulting Offer is the existing canonical `ActorProfileIntent`, not another
market record. Its metadata preserves:

- Business UUID;
- Business Listing UUID;
- exact immutable ListingVersion UUID;
- attached BusinessContact UUID when present.

When a new ListingVersion is later published, the older Business-generated Offer is
closed and the new exact version becomes the active Offer.

A Business client/contact may similarly create a canonical Need from the CRM. The
Business remains the office/customer-management context; the existing Intent matcher
remains the only matching engine.

Independent actors may type the same normalized concept label. Matching may use that
exact normalized-label equality as a deterministic fallback when the concepts have
not yet been canonically merged, but subject/arrangement and all other compatibility
rules still apply. This does **not** mutate or silently merge the semantic Concepts.

## Canonical provenance through the deal

The expected chain is:

```text
Business ListingVersion
    ↓ exact provenance
Offer Intent
    ↔ deterministic matcher
Need Intent
    ↓ explicit user handoff
Relationship / Deal
    ↓
ProposalVersion
    ↓ accepted exact terms
ContractVersion
    ↓
Commitment
    ↓
Fulfillment + explicit review
    ↓
FinancialObligation (IET)
    ↓
Settlement
    ↓
funded IET wallet / cash-out readiness
```

No second Deal, Contract, accounting, wallet or matching kernel is introduced.

## Browser acceptance walkthrough

### A. See the businesses

1. Login as `test@example.com`.
2. Open Organizations → Businesses.
3. Confirm the four businesses above are visible.
4. Open **Safdar Real Estate Office** and confirm Real Estate appears as a specialized
   channel inside the Business rather than as its own top-level menu.

### B. See Business supply in the market

1. Open Atlas Inspection Services → Catalog.
2. The published inspection Listing should show **Active in Needs & Offers**.
3. Open its matches.
4. Confirm a compatible buyer Need is discoverable.
5. Confirm the Business page shows market / Deal / Contract / IET projection derived
   from canonical records.

### C. See a debtor

1. Login as `tool-buyer@example.com`.
2. Open Money.
3. Confirm net IET position is **−80** with 80 payable and zero funded wallet.
4. The page should explain the two recovery paths:
   - deposit via the placeholder Exchange; or
   - publish skills/services/goods and earn IET through accepted work.

### D. See earning beyond debt

Automated acceptance covers:

1. user owes 100 IET;
2. the same user provides accepted work worth 150 IET;
3. wallet remains 0;
4. receivable becomes 150;
5. payable remains 100;
6. net internal position becomes **+50 IET**.

This proves debt recovery is not limited to cash deposits.

### E. See funded settlement and cash-out readiness

1. Login as `inspection-buyer@example.com` and confirm the seeded completed scenario
   has net position 0.
2. Login as `test@example.com`.
3. Money should show wallet 120 IET plus outstanding receivables.
4. The 120 funded IET is immediately eligible for a placeholder cash-out request.
5. The remaining 430 IET receivable contributes to net internal position but is not
   yet externally cash-out eligible.

## Automated acceptance

The focused acceptance tests are:

```bash
php artisan test --compact \
  tests/Feature/CoherenceBaselineBusinessEconomyTest.php \
  tests/Feature/CoherenceBaselineDemoSeederTest.php
```

They cover:

- exact ListingVersion provenance through market/Deal/Proposal/Contract;
- canonical seeded Business Offer → Need → Deal → Contract traceability;
- independently created equivalent market labels;
- accepted work creating IET debt/credit positions;
- four economic scenarios;
- placeholder deposit;
- IET settlement;
- provider wallet funding;
- cash-out readiness;
- earning more than an existing debt;
- repeatable visible demo seeding;
- Business receivable and confirmed-settlement realized revenue/profit projection;
- Business operating-shell rendering of the IET projection.

## Verified remote gate

Verified implementation head:

`f19f0f11cdb9c477357cb75ad2f575e12aa1789e`

Remote CI completed green with:

- **728 tests / 6734 assertions**;
- Pint green;
- PHPStan green;
- MySQL migration portability green;
- rollback/reapply + scheduler/queue smoke green;
- SQLite backup/restore green;
- frontend build green;
- npm audit: 0 vulnerabilities;
- Composer: no security vulnerability advisories.

## Boundary for the next review layer

This baseline intentionally leaves real external banking/payment-provider integration
disabled. Deposit and cash-out remain reviewed placeholder requests.

After C5-C7 is accepted, the next review should focus on product composition and
language rather than inventing more kernels: fewer pages, stronger next actions,
clearer Business/client/listing/Deal cross-links, better mobile/RTL presentation, and
end-to-end browser friction found while using the seeded scenarios.
