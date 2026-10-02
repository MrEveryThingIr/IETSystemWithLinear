# Coherence-First Execution Roadmap

## Why this roadmap exists

This document defines the execution order after the 2026-10-01 full-system review
and the Business vertical consolidation.

The review concluded that IET's primary weakness is now product composition rather
than missing domain capability. Owner browser acceptance confirmed the same thing:
individual kernels are increasingly capable, but a simple user still has to
understand too much of the internal architecture to know what to do next.

Therefore the next work is **coherence-first**.

This roadmap does not replace:

- `docs/IET-FULL-SYSTEM-REVIEW-2026-10-01.md`;
- `docs/EXPERIENCE-COMPOSITION-ROADMAP.md`;
- `docs/BUSINESS-PLATFORM-ROADMAP.md`.

It defines which parts of those roadmaps should be executed next and in what order.

## Product rule

A simple user should rarely need to choose a subsystem.

The system should answer four questions on every important screen:

1. Where am I?
2. What is happening now?
3. What should I do next?
4. What happens if I do it?

Internal domain nouns remain authoritative, but they should appear only when useful.

## Current state

Completed foundations:

- preservation / characterization baseline;
- experience-navigation grouping;
- administration/help/labs separation;
- Business as reusable operating container;
- Real Estate demoted from platform peer to Business vertical;
- Business CRM, catalog, immutable listing versions, append-only price versions;
- Business Context and Business-scoped Planner routines;
- IET-first Business settlement boundary;
- exact-head CI acceptance for Business vertical consolidation;
- C1 Today/onboarding/Next Action composition is engineering-complete with exact-head CI;
- C2 goal-oriented destination hubs are engineering-complete with exact-head CI;
- C3 contextual workflow shells are engineering-complete with exact-head CI;
- C4 traditional-user Business completion is engineering-complete with exact-head CI.

Still weak:

- Deal workflow still requires the user to understand Relationship / Proposal /
  Contract / Commitment / obligation as separate facilities;
- Business shell is structurally richer but not yet a polished traditional-user journey;
- Content authoring remains too fragmented;
- Group experience remains administration-heavy;
- cross-domain next action / consequence language is inconsistent;
- empty states and success transitions do not consistently lead onward.

# Execution sequence

## C1 — Today, onboarding, and Next Action

Status: **complete on `codex/coherence-c1-today-next-action`**.

Goal: make IET immediately understandable after login.

Implemented:

- Today derives a minimal three-step setup checklist from existing Actor/Profile/contact/activity state;
- one deterministic recommended next action, with explicit priority and no AI guessing;
- actions requiring the user outrank optional setup;
- in-progress/upcoming Planner work is surfaced before low-priority guidance;
- outstanding money, matches and active work can become the next action when appropriate;
- first-time users choose a human goal instead of a subsystem;
- verification-success feedback is transient instead of permanently displayed;
- `/getting-started` remains compatible but lands on Today's live checklist;
- secondary summaries are progressively disclosed under “More from my account”;
- advanced Accounting and kernel terminology are removed from the normal Today flow;
- EN/FA/AR/ZH guidance copy and regression coverage.

Acceptance evidence:

- full CI green on the completed implementation;
- 700 PHPUnit tests passed;
- Pint and PHPStan green;
- MySQL migration portability green;
- rollback/reapply, scheduler and queue smoke green;
- SQLite backup/restore green;
- frontend build and JavaScript audit green;
- Composer security audit green;
- owner browser review remains the product-feel checkpoint, but no C1 engineering work is pending.

The next active milestone is **C2 — Real goal-oriented destination hubs**.

Original implementation intent:

- remove persistent verification-success noise;
- state-derived Getting Started / setup checklist on Today;
- deterministic NextAction projection derived from authoritative records;
- Today sections:
  - Needs my attention;
  - Next recommended action;
  - Today / upcoming;
  - Waiting on others;
  - Recent outcomes;
- each action explains:
  - why it is shown;
  - who needs to act;
  - what record it affects;
  - what happens next;
- empty Today should guide the user toward a useful first action instead of showing
  an empty dashboard;
- onboarding should adapt to role/state rather than be a one-time static page.

Do not create a new workflow-state table. Derive from Profile completeness, Intents,
Relationships, Proposals, Contracts, Commitments, Planner occurrences, submissions,
Business membership, obligations, and existing projections.

Browser acceptance:

A new or ordinary user should be able to login and answer within seconds:
"What needs my attention and what should I do next?"

## C2 — Real goal-oriented destination hubs

Status: **engineering-complete on `codex/coherence-c2-goal-oriented-hubs`; owner browser review remains the product-feel checkpoint**.

Goal: the sidebar destinations become actual coherent products rather than labels
that expand into subsystem links.

Implemented:

- primary sidebar is flat and intention-oriented: Today, Needs & Offers, Work,
  Organizations, Money, Content;
- Help is one destination rather than separate Manual / System Map entries;
- Needs & Offers composes Mine / Discover / Matches while preserving intent semantics;
- Work composes attention, active Deals, and Planner schedule without duplicating
  workflow state;
- Organizations composes Businesses and Groups while preserving their distinct
  domain models;
- Money is now the simple daily entry at `/money`;
- detailed personal ledgers remain available at `/money/accounts`;
- Accounting and Exchange are progressively disclosed as advanced Money tools;
- outstanding Money summaries remain separated by monetary unit and never add
  incomparable minor units together;
- Content composes My Content / Explore / Create;
- all hubs respect underlying publication grants and hide unpublished sections rather
  than rendering disabled/empty subsystem cards;
- existing deep domain routes remain intact;
- EN / FA / AR / ZH hub copy added;
- shared hub page contract uses purpose → live summary → primary action → deeper tools;
- no new persistent workflow-state table or duplicate domain kernel was introduced.

Acceptance evidence:

- implementation SHA `c53e093f3f7d6608e148518c5cebc7ead65b9ef4`;
- MySQL migration portability: green;
- frontend build and JavaScript audit: green;
- Pint: green;
- PHPStan: green;
- rollback/reapply, scheduler, and queue smoke: green;
- SQLite backup/restore: green;
- PHPUnit: **708 passed / 5720 assertions**;
- Composer security audit: no vulnerability advisories.

Browser acceptance focus:

- ordinary sidebar destinations should be one click, without choosing subsystems first;
- partial publication must never leak unavailable domains into a hub;
- Persian/Arabic RTL must remain clear;
- Money should feel simple before Accounting appears;
- a novice should understand where to go without knowing which IET kernel owns the task.

The next active milestone is **C3 — Contextual workflow shells**.

## C3 — Standard page contract and contextual workflow shells

Status: **engineering-complete on `codex/coherence-c3-contextual-workflow-shells`; owner browser/product-feel acceptance pending**.

Verified implementation head: `982b2ebd85884793bd064fd55a58c8bef238411e` — CI green with **715 tests / 6118 assertions**, Pint, PHPStan, MySQL portability, rollback/reapply + scheduler/queue smoke, SQLite backup/restore, frontend build + npm audit, and Composer security audit.

Goal: every important workflow teaches itself.

Implemented:

- shared workflow-shell contract for purpose, current state, dominant next action,
  audience/visibility, durable result, consequence, contextual help and progressively
  disclosed advanced controls;
- Deal shell presents the human pipeline
  Need/Offer → Match → Deal → Terms → Agreement → Work → Proof/Review → Settlement
  while preserving the existing `DealPipeline`, Relationship, Proposal, Contract,
  Commitment, Fulfillment and FinancialObligation authorities;
- Business shell derives a deterministic next operating step from existing Business
  state (contact/location, clients, catalog, Planner routines), keeps CRM, catalog,
  Planner, Deal and Money records authoritative, and hides unpublished Planner / Deal /
  Money siblings instead of leaking unavailable capabilities;
- Group shell prioritizes Community / Spaces / Content and moves governance-oriented
  settings behind a Manage disclosure while keeping urgent review work visible;
- Content Studio now teaches Write → Structure → Media → Preview → Publish, keeps
  Publish as the single dominant action, makes publication readiness informational, and
  moves AI, blocks and appearance into Advanced rather than presenting them as required
  starting choices;
- English, Persian, Arabic and Simplified Chinese workflow copy added without replacing
  the pre-existing Content workflow language keys;
- focused C3 regression coverage added in
  `tests/Feature/ContextualWorkflowShellTest.php`;
- browser acceptance exposed a temporal-state gap: an accepted ContractVersion could remain
  “waiting for effective time” after its effective instant when the scheduler was not
  running locally. Contract pages now reconcile due activation for that Contract on
  open/Livewire interaction while `contracts:activate-due` remains the background safety
  net; regression coverage proves unrelated Contracts are not activated by the read;
- no new migration, workflow-state table or domain kernel introduced.

Every major page should consistently show:

1. purpose;
2. current state;
3. one dominant next action;
4. at most two secondary actions before Advanced;
5. back / close / cancel;
6. durable result;
7. audience / visibility;
8. consequence boundary;
9. contextual help;
10. successful onward step.

Build shells for:

### Deal shell

Display one human pipeline:

Need / Offer
→ Match
→ Deal
→ Terms
→ Agreement
→ Work
→ Proof / review
→ Payment / settlement

Keep Relationship, Proposal, Contract, Commitment, Fulfillment and
FinancialObligation internally authoritative.

### Business shell

Default sections:

- Overview;
- Clients;
- Catalog;
- Work;
- Deals;
- Money;
- Team / Settings.

Specialized vertical capabilities appear inside this shell.

### Group shell

Default sections:

- Overview / Community;
- Work / Spaces;
- Content;
- Manage.

Governance, roles, agreements, invitations and ownership move behind Manage unless
the user currently needs to act on them.

### Content shell

Default flow:

Write
→ Structure
→ Media
→ Preview
→ Publish

Advanced appearance / blocks / AI remain available but progressively disclosed.

Browser acceptance:

A user should be able to continue a workflow without understanding the database
model or remembering which page owns the next stage.

## C4 — Traditional-user Business completion

Status: **engineering-complete on `codex/coherence-c4-traditional-business-completion`; owner browser/product-feel acceptance pending**.

Verified implementation head: `e79f71ef4c41ef00860c0b5c64ab6254ed459ee7` — CI green with **720 tests / 6601 assertions**, Pint, PHPStan, MySQL portability, rollback/reapply + scheduler/queue smoke, SQLite backup/restore, frontend build + npm audit, and Composer security audit.

Goal: execute Business roadmap M5 and M6 without creating another media,
publishing, property, or workflow kernel.

Implemented:

- canonical Business Listing / immutable ListingVersion remains the commercial source
  of truth;
- ordered Asset-backed Listing media supports photos, video/audio-compatible Assets,
  cover role, captions and public/member/private visibility;
- public Listing media reuses the existing Content publication-evidence rules, so
  unresolved rights/readiness cannot silently become published customer media;
- generated customer presentation reuses the existing Business Context and Content
  kernel rather than introducing a second publishing engine;
- simple catalog-item editor is the normal path; the advanced presentation editor is
  optional and progressively disclosed;
- customer preview deliberately excludes attached client identity, exact address and
  private office notes;
- published ListingVersions remain immutable; later edits create a new working version
  and preserve prior property/media history;
- Real Estate property details now cover transaction/location, dimensions, building
  characteristics, floor/unit details, parking, elevator, storage, balcony,
  utilities/facilities, deed/usage/occupancy, coordinates and public/private notes;
- Persian and Arabic digits are normalized for property numerics and price entry;
- traditional Real Estate flow is composed as
  Client → Purpose/location → Property → Building/facilities → Price → Media →
  Preview → Publish;
- simple-office mode and explicit availability lifecycle are built into the canonical
  Business Listing;
- reviewed public Real Estate offers retain their real-world BusinessContact provenance
  when promoted into a Property catalog item;
- ordinary UI copy avoids generic Content Studio / internal-kernel terminology;
- scheduled availability fields remain in the domain model but are intentionally not
  exposed through native Gregorian browser controls; future editing must use the shared
  profile-aware temporal fabric;
- EN / FA / AR / ZH copy and focused regression coverage added.

Acceptance evidence:

- MySQL migration portability: green;
- frontend build and JavaScript audit: green;
- Pint: green;
- PHPStan: green;
- rollback/reapply, scheduler and queue smoke: green;
- SQLite backup/restore: green;
- PHPUnit: **720 passed / 6601 assertions**;
- Composer security audit: no vulnerability advisories.

Browser acceptance focus:

- create/open a Real Estate Business catalog property and attach an existing client;
- enter property dimensions/prices with Persian digits;
- keep exact address/private notes internal while public area/details appear in preview;
- attach owned public media, choose a cover, caption/reorder it and preview it;
- publish the property and optionally open its advanced presentation editor;
- edit the published property and confirm a new working version is created while the
  published historical version remains unchanged;
- confirm public media with unresolved rights blocks publication.

A traditional real-estate office user should be able to register a client and
property without encountering generic platform terminology.

The next active milestone is **C5 — Complete the Business-to-market-to-deal journey**.

## C5 — Complete the Business-to-market-to-deal journey

Status: **engineering-complete in the coherence baseline; owner browser/product-feel review remains part of the next review layer**.

Implemented:

- an exact published Business ListingVersion can publish into the existing canonical
  Offer Intent;
- a BusinessContact can publish demand into the existing canonical Need Intent;
- Business UUID, Listing UUID, exact immutable ListingVersion UUID and client
  provenance are retained in market metadata;
- publishing a later ListingVersion closes the older Business-generated active Offer
  instead of mutating its historical version provenance;
- the existing Intent matcher remains the only matching engine;
- independently-created equivalent normalized labels may match deterministically
  without silently merging semantic Concepts;
- Need + Offer create the existing Relationship/Deal, not a Business-specific deal;
- Deal-sourced Proposal/Contract preserves the canonical Relationship and exact market
  provenance;
- accepted work continues through the existing Commitment/Fulfillment kernels.

Visible baseline:

- Catalog items expose publication to Needs & Offers;
- Business CRM clients expose publication of canonical Needs;
- active Business-generated market records are back-projected into Catalog/CRM so the
  handoff is not one-way;
- Business pages project market postings, Deals and Contracts from the same canonical
  records;
- the local acceptance world seeds four Business scenarios with real
  Offer → Need → Deal → Contract provenance.

## C6 — Business finance and IET settlement projection

Status: **engineering-complete for the internal-settlement baseline; real bank/payment-provider integrations remain intentionally disabled placeholders**.

Implemented over the existing finance kernels:

- human-facing IET net position =
  funded wallet + outstanding receivables − outstanding payables;
- the receiver of accepted value may therefore show a negative net position without
  creating a fake negative wallet;
- the provider immediately receives a positive internal position as an outstanding
  receivable;
- a debtor may work their way out of debt by providing accepted value; if they earn
  more than they owe, the net position becomes positive;
- external cash-out remains stricter than internal position and is limited to actually
  funded wallet value;
- placeholder Exchange deposit can fund an IET debtor;
- confirmed IET Settlement atomically decreases debtor wallet and funds creditor wallet;
- Business projection shows open IET receivables/payables plus realized settled
  revenue/expense/profit;
- Business financial projection is traced through canonical market Deal → Contract →
  obligation/settlement records;
- no second wallet, accounting, Deal, Contract or settlement engine was introduced.

Seeded acceptance positions:

- testuser/provider: wallet 120, receivable 430, payable 0, net **+550 IET**,
  cash-out eligible **120 IET**;
- inspection buyer: net **0 IET** after placeholder deposit + settlement;
- tool buyer: **−80 IET**;
- home-service buyer: **−150 IET**;
- property buyer: **−200 IET**.

## C7 — Release acceptance and simplification pass

Status: **engineering-complete baseline; ready for the next whole-product review/improvement layer**.

Completed baseline work:

- visible, repeatable local demo world rather than hidden model-only fixtures;
- four Businesses:
  Safdar Real Estate Office, Atlas Inspection Services, Everyday Tools Store and
  Bright Home Services;
- ordinary user-facing flows reuse goal-oriented hubs and contextual workflow shells;
- Business Catalog/CRM → market handoff has an obvious next action;
- Money exposes net IET position, debt-recovery choices, deposit/cash-out boundary and
  funded-vs-unfunded distinction;
- Business operating page exposes canonical market/Deal/Contract/economic projection;
- Real Estate remains a Business vertical, not another top-level subsystem;
- EN / FA / AR / ZH copy was extended for the Money/IET baseline;
- focused render/seed/provenance/economy regressions cover visible integration;
- exact-head engineering gate on the implementation head
  `f19f0f11cdb9c477357cb75ad2f575e12aa1789e`:
  **728 tests / 6734 assertions**, Pint green, PHPStan green, MySQL portability green,
  rollback/reapply + scheduler/queue smoke green, SQLite backup/restore green,
  frontend build green, npm audit 0 vulnerabilities, Composer reports no vulnerability
  advisories.

The baseline is deliberately not called the final product design. The next phase is a
fresh whole-system review against real browser use, followed by another coherence and
UX improvement layer.

# Canonical end-to-end acceptance stories

## Personal user

Invitation
→ registration
→ identity/contact setup
→ create Need
→ discover/match
→ Deal
→ terms/agreement
→ execution
→ settlement
→ Today reflects completion.

## Business user

Create/open Business
→ add client
→ add good/service/property
→ media/presentation
→ price
→ publish Offer
→ record another client's Need
→ match
→ Deal
→ Proposal
→ Contract
→ execution
→ settlement
→ Business Money shows result.

## Real Estate traditional-user story

Open Safdar Real Estate Office
→ add Mr. Ahmad
→ record Ahmad's property
→ add photos
→ publish property Offer
→ add Mr. Reza
→ record Reza's Need
→ matching identifies Ahmad's property
→ open Deal
→ agree final terms
→ Contract
→ fulfillment / commission
→ settlement
→ Business financial projection shows income.

# What not to do next

Until C1–C3 are accepted in the browser:

- do not add another top-level facility;
- do not create another standalone vertical;
- do not expand administration into normal navigation;
- do not duplicate workflow state;
- do not build a second market, content, planner, wallet or accounting engine;
- do not prioritize deep specialist functionality over orientation and next-action
  guidance.

The C1–C7 coherence roadmap is now engineering-complete as a baseline. The next work is a fresh whole-system browser review and a new improvement layer, not another unchecked feature expansion.
