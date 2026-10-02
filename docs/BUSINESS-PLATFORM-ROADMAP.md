# Business Platform Roadmap

## Authority and intent

This roadmap combines:

- the product-composition direction in `docs/IET-FULL-SYSTEM-REVIEW-2026-10-01.md`;
- the Business / Catalog / Goods / Services / Property architecture brief supplied during the Business redesign;
- the current implementation state on the Business vertical consolidation branch.

The Business layer is not a second marketplace, content engine, planner, accounting engine, or identity system.
It is an operating container that reuses the authoritative IET kernels beneath it.

## Canonical product model

```text
Business
 ├─ members / staff
 ├─ real-world clients / CRM
 ├─ categories
 ├─ Business Listings
 │    ├─ immutable Listing Versions
 │    ├─ immutable Price Versions
 │    ├─ vertical details
 │    └─ Content / Asset presentation
 ├─ Business Context
 │    ├─ Planner routines
 │    ├─ Content
 │    ├─ conversations / evidence
 │    └─ Accounting projections
 └─ specialized intake channels
      └─ Real Estate is the first vertical

Business Listing Version
        ↓ publish to existing market
Need / Offer
        ↓
Relationship / Deal
        ↓
Proposal
        ↓
Contract
        ↓
Commitment / fulfillment
        ↓
Settlement
        ↓
Accounting
```

## Non-negotiable architectural rules

1. Do not create a second marketplace. Catalog publication must eventually produce or reference the existing Need/Offer records.
2. Do not create another Content system. Listings stay structured truth; Content/Assets provide presentation and media.
3. Do not create another accounting/balance engine. Catalog prices are descriptive; contracts, settlements, journals, and ledgers remain financial truth.
4. Published Listing versions are immutable.
5. Price history is append-only and keeps its original monetary-unit identity.
6. Real-world customers do not need an IET account. BusinessContact remains valid independently of User/Actor.
7. Business users should not need to understand internal IET kernel nouns.
8. Specialized industries extend BusinessListing instead of adding unrelated platform subsystems.
9. Proposal/Contract provenance should reference exact Listing/Need versions.
10. Existing domain policies and Feature Surface publication remain authoritative.

## Current milestone — Business vertical consolidation

Status: **implemented; CI and owner browser acceptance pending**.

Implemented:

- Real Estate removed from ordinary navigation as an independent destination;
- legacy `/workspace/real-estate` retained only as a compatibility redirect into Businesses;
- existing public/admin office URLs remain valid;
- existing Real Estate office can be adopted into a Business without changing its UUID/token or deleting cases;
- Business gains one reusable Business Context;
- Planner can create and operate Business-context routines;
- Business CRM for unregistered real-world clients;
- hierarchical Business categories;
- generic `BusinessListing` model for goods, services, properties, and future vertical types;
- immutable `BusinessListingVersion`;
- immutable `BusinessPriceVersion`;
- structured property extension on Listing Versions;
- reviewed Real Estate offers can be promoted into generic Business Listings;
- IET is the default internal settlement unit for newly-created Businesses;
- external bank/payment integrations are placeholders only;
- local Real Estate demo bootstrap preserves the requested office UUID/token and seeds a realistic offer/need scenario.

## Browser acceptance for this milestone

A successful browser review should feel like this:

1. Real Estate is no longer a peer of Businesses in the sidebar.
2. Businesses feels like the place where an owner runs a shop, office, service company, workshop, or Real Estate office.
3. Opening a Business exposes Clients, Catalog, Business routines, team/contact/location, and only the specialized channels that apply to that Business.
4. The historical Real Estate office still opens at the same old URLs.
5. A legacy office can be adopted into Business explicitly rather than silently rewritten.
6. A reviewed property Offer can be promoted to the generic catalog.
7. Goods, Services, and Properties visibly share the same catalog model.
8. A normal user never sees technical concepts such as BusinessContext or BusinessListingVersion as required workflow knowledge.

## Delivery sequence

### M1 — Business foundation

Status: **complete / strengthened**.

- Business identity and ownership;
- members and roles;
- contact/location identity;
- Business Context;
- operating dashboard.

### M2 — Business CRM

Status: **complete for first release**.

- BusinessContact independent of User registration;
- primary and secondary contact points;
- address and private notes;
- source/referrer;
- archive instead of destructive deletion;
- legacy Real Estate contacts migrate to Business ownership during adoption.

Later:

- tags;
- linking a Contact to Actor after registration;
- relationship/deal summary per client;
- deduplication review tools.

### M3 — Generic catalog

Status: **foundation complete**.

- hierarchical categories;
- generic Listing;
- listing types: good / service / property / other;
- listing visibility and lifecycle;
- immutable versions.

Next:

- richer editor;
- tags;
- media/Content projection;
- bulk operations;
- search/filtering.

### M4 — Pricing

Status: **foundation complete**.

- append-only price versions;
- price type;
- monetary unit;
- basis;
- visibility;
- validity window;
- change reason.

Next:

- authorized private cost prices;
- active-price resolver;
- quotations;
- derived display of unit prices;
- explicit cost/sell distinction for goods.

### M5 — Content and media integration

Status: **planned**.

- Listing structured data remains canonical;
- generate/update Listing presentation Content;
- Asset-based photo/video/audio;
- cover/reorder/caption workflow;
- preview and publication;
- simple editor by default, advanced Content Studio by choice.

### M6 — Real Estate vertical

Status: **first vertical foundation complete**.

Current:

- property detail extension;
- preserved public intake;
- preserved office case administration;
- Business adoption;
- Property Offer → Business Listing promotion;
- seeded property taxonomy.

Next:

- full professional property schema from the brief;
- wizard: client → transaction/location → dimensions/building → facilities → prices → media → preview → publish;
- Persian-number friendly inputs;
- simple-office mode;
- public/private property fields;
- property lifecycle and availability.

### M7 — Market integration

Status: **next major domain milestone**.

- Listing Version → existing Offer;
- BusinessContact demand → existing Need;
- no second matching engine;
- retain exact Listing Version provenance;
- match Business-managed Need against published Offers;
- safe synchronization when Listing gets a new version.

### M8 — Deal integration

Status: **planned after M7**.

- matched Need/Offer → existing Relationship/Deal;
- Business dashboard shows open Deals;
- client and Listing records link to the same canonical Deal.

### M9 — Proposal / Contract provenance

Status: **planned**.

- Proposal references exact Listing/Need versions;
- accepted terms create/use Contract;
- later Listing edits cannot change historical negotiated terms;
- Contract preserves monetary-unit and price provenance.

### M10 — Business finance and IET settlement

Status: **foundation boundary established**.

Policy:

- internal platform settlement defaults to IET;
- Business routines and contracts may estimate/display external monetary units;
- actual internal obligation/settlement/accounting remains in authoritative financial kernels;
- external deposit/cashout gateways are adapters, not another wallet/balance truth.

Next:

- Business accounting projection from existing ledgers/obligations/settlements;
- receivables/payables;
- realized revenue/expense/profit projections;
- IET deposit/cashout request flow through provider adapters;
- bank/payment-provider implementations only after provider/security/audit decisions.

### M11 — UX hardening and traditional-user acceptance

Status: **ongoing across every milestone**.

- Persian-first Real Estate office acceptance;
- large primary actions;
- few mandatory fields;
- progressive disclosure;
- autosave drafts where applicable;
- plain-language stage/next-action guidance;
- desktop/mobile/RTL/keyboard/focus;
- novice / ordinary / manager / operator personas.

## First full acceptance story

The canonical acceptance story remains:

1. create Safdar Real Estate Office as a Business;
2. add Mr. Ahmad as a real-world client;
3. record Ahmad's property;
4. attach photos/media;
5. publish the Property Listing as an Offer;
6. add Mr. Reza as another real-world client;
7. record Reza's property Need;
8. existing matching identifies Ahmad's property;
9. start Deal/Relationship;
10. Proposal negotiates final price/conditions;
11. accepted Proposal creates/feeds Contract;
12. fulfillment/commission/payment produces obligations and Settlement;
13. Business financial projection shows the resulting income.

The product is not considered fully validated until this story works naturally for a traditional office user without exposing implementation-level IET terminology.

## Relationship to the system-experience roadmap

The Business roadmap does not replace `docs/EXPERIENCE-COMPOSITION-ROADMAP.md`.

The two tracks now run together:

- **Experience track:** Today, onboarding, progressive disclosure, consolidated navigation, contextual shells.
- **Business track:** CRM, catalog, verticals, market/deal provenance, IET settlement projection.

The shared rule is the same: preserve authoritative kernels and improve composition around human goals.
