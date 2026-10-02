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
- public Business identity remains viewable when configured public, while CRM/catalog operations/routines/team/office cases remain member-only;
- hierarchical Business categories;
- generic `BusinessListing` model for goods, services, properties, and future vertical types;
- immutable `BusinessListingVersion`;
- immutable `BusinessPriceVersion`;
- structured property extension on Listing Versions;
- reviewed Real Estate offers can be promoted into generic Business Listings;
- IET is the default internal settlement unit for newly-created Businesses;
- Business Planner expense estimates default to the Business settlement unit (IET by default);
- catalog price entry uses exact minor-unit parsing and append-only price history;
- external bank/payment integrations are placeholders only and are shown as non-executable in the Business dashboard;
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

Status: **first browser-operable release complete**.

- append-only price versions;
- price type;
- monetary unit;
- basis;
- visibility;
- validity window;
- change reason;
- browser price entry with exact decimal-to-minor conversion;
- Business default unit preselection.

Next:

- active-price resolver;
- quotations;
- derived display of unit prices;
- explicit cost/sell distinction for goods.

### M5 — Content and media integration

Status: **engineering-complete in C4**.

Implemented:

- Listing structured data and immutable ListingVersions remain canonical;
- generated customer presentation uses the existing Business Context + Content kernel;
- Asset-based media with cover role, ordering, captions and visibility;
- publication reuses existing media rights/readiness evidence;
- privacy-safe preview and coordinated Listing/presentation publication;
- simple editor by default, advanced presentation editor by choice;
- later Listing edits create a new working version rather than mutating published
  commercial or presentation history.

### M6 — Real Estate vertical

Status: **traditional-user implementation engineering-complete in C4; owner browser acceptance pending**.

Implemented:

- full professional property extension covering transaction/location, dimensions,
  building/unit details, facilities, deed/usage/occupancy and public/private notes;
- preserved public intake and office case administration;
- Business adoption and Property Offer → Business catalog promotion;
- promoted cases preserve their BusinessContact provenance;
- seeded property taxonomy remains reused;
- guided flow: client → purpose/location → property → building/facilities → price →
  media → preview → publish;
- Persian/Arabic-number friendly numeric and price entry;
- simple-office mode;
- explicit public/private property fields;
- Listing availability lifecycle;
- customer preview excludes exact address, client identity and private office notes;
- no duplicate Real Estate publishing/media kernel was introduced.

### M7 — Market integration

Status: **engineering-complete for the coherence baseline**.

- exact published ListingVersion → existing canonical Offer Intent;
- BusinessContact demand → existing canonical Need Intent;
- no second matching engine;
- Business / Listing / exact ListingVersion / client provenance retained;
- later Listing publication closes superseded Business-generated Offers rather than
  rewriting historical provenance;
- Catalog and CRM back-project active market state so publication is visible and
  reversible from the user's mental model.

### M8 — Deal integration

Status: **engineering-complete for the coherence baseline**.

- matched Need/Offer → existing Relationship/Deal;
- seeded Business scenarios create real canonical Deals;
- Business projection counts the same Deals instead of Business-specific duplicates;
- Deal keeps originating Need and matched Offer references.

### M9 — Proposal / Contract provenance

Status: **engineering-complete for the coherence baseline**.

- Proposal/Contract continue through the existing canonical kernels;
- Deal-sourced Contract retains Relationship provenance;
- market Offer metadata pins the immutable ListingVersion that was negotiated;
- later Listing edits cannot mutate already-published ListingVersions or negotiated
  Contract history;
- existing monetary-unit identity remains authoritative.

### M10 — Business finance and IET settlement

Status: **engineering-complete for the internal-settlement baseline**.

Policy and implementation:

- internal platform settlement defaults to IET where the agreement chooses IET;
- no second Business balance engine exists;
- Business finance is a projection over canonical Contracts, FinancialObligations and
  confirmed Settlements;
- Business projection shows open receivables/payables and realized
  revenue/expense/profit in IET;
- personal Money shows wallet + receivable − payable as net IET position;
- accepted value can make the receiver negative and provider positive before funding,
  while immutable obligations still show exactly who owes whom;
- debt can be covered by placeholder deposit or by later accepted work that creates
  receivables;
- cash-out eligibility remains limited to funded wallet value;
- Exchange remains the adapter boundary for future real providers;
- real bank/payment-provider execution stays disabled until provider/security/legal
  and audit decisions are made.

### M11 — UX hardening and traditional-user acceptance

Status: **coherence baseline engineering-complete; next whole-product browser review pending**.

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
