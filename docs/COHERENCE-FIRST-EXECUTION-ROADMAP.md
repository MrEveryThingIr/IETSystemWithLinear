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
- exact-head CI acceptance for Business vertical consolidation.

Still weak:

- Today does not yet behave as a personal operating guide;
- onboarding is not state-derived and persistent;
- top-level destinations are grouped but not yet true task-oriented hubs;
- Needs/Offers, Work, Money and Content still expose overlapping internal entry points;
- Deal workflow still requires the user to understand Relationship / Proposal /
  Contract / Commitment / obligation as separate facilities;
- Business shell is structurally richer but not yet a polished traditional-user journey;
- Content authoring remains too fragmented;
- Group experience remains administration-heavy;
- cross-domain next action / consequence language is inconsistent;
- empty states and success transitions do not consistently lead onward.

# Execution sequence

## C1 — Today, onboarding, and Next Action

Priority: **next**.

Goal: make IET immediately understandable after login.

Implement:

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

Goal: the sidebar destinations become actual coherent products rather than labels
that expand into subsystem links.

Implement five task-oriented hubs plus Today:

### Needs & Offers

One entry with modes:

- Mine;
- Discover;
- Matches.

Hide the difference between "Profile intents" and "Market intents" from normal
navigation while preserving their record semantics.

### Work

One entry that composes:

- active Deals;
- Planner activities;
- commitments / execution;
- waiting actions.

A simple user should not choose "Deals or Planner?" before the system explains why.

### Organizations

One entry that composes:

- Businesses;
- Groups.

A Business is an operating organization.
A Group remains a governed collaboration/community concept.
Do not collapse their domain models.

### Money

One simple daily experience:

- balance / accounts;
- money activity;
- obligations / receivables / payables;
- settlement attention.

Accounting becomes Advanced.
Exchange becomes contextual when deposit/cashout is relevant or explicitly opened.

### Content

One entry with:

- My Content;
- Explore / Library;
- Create.

Outline, Blocks, Appearance, Assets and AI become steps/tools inside authoring,
not peer destinations.

### Help

Manual + System Map + contextual help become one Help center.

Browser acceptance:

A simple user should navigate by intention, not by knowing which IET subsystem owns
the task.

## C3 — Standard page contract and contextual workflow shells

Goal: every important workflow teaches itself.

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

Execute Business roadmap M5 and M6 after the shells are coherent.

### M5 Content / media integration

- Listing structured data remains canonical;
- Asset-backed photos/video/audio;
- cover/reorder/captions;
- generated Listing presentation Content;
- preview/publish;
- simple Listing editor first, advanced Content Studio optional.

### M6 Real Estate vertical

- professional property schema from the Business brief;
- Persian-number tolerant inputs;
- wizard:
  Client
  → Purpose / transaction
  → Location
  → Property
  → Building / facilities
  → Price
  → Media
  → Preview
  → Publish;
- simple-office mode;
- private vs public fields;
- lifecycle / availability.

Browser acceptance:

A traditional real-estate office user should be able to register a client and
property without encountering generic platform terminology.

## C5 — Complete the Business-to-market-to-deal journey

Execute Business roadmap M7–M9.

### M7 Market integration

- Listing Version → existing Offer;
- BusinessContact demand → existing Need;
- exact version provenance;
- existing matcher only;
- safe synchronization when a Listing gets a new version.

### M8 Deal integration

- match → canonical Deal / Relationship;
- Business dashboard and client/listing pages show the same Deal;
- no duplicate deal records.

### M9 Proposal / Contract provenance

- proposal references exact Need/Listing versions;
- accepted terms feed Contract;
- later Listing edits never mutate negotiated history;
- monetary-unit provenance preserved.

Browser acceptance:

From a Business, the user can publish supply/demand, see a match, open the deal,
agree terms and continue execution without navigating the internal kernels manually.

## C6 — Business finance and IET settlement projection

Execute Business roadmap M10.

Implement over the existing finance kernels:

- Business receivables / payables;
- realized revenue / expense / profit projection;
- contract/settlement/accounting traceability;
- IET deposit / cashout request flow via adapters;
- bank/payment-provider integrations remain disabled placeholders until a real
  provider, legal/security model and audit are selected.

Browser acceptance:

The Business Money page explains:
what is owed, what is settled, what moved in IET, and what can/cannot be cashed out.

## C7 — Release acceptance and simplification pass

Combine Experience Phase 5 and Business M11.

Personas:

- invited newcomer;
- ordinary personal user;
- traditional Business owner;
- Business staff member;
- Group member/manager;
- reviewer/operator;
- platform administrator.

Check:

- English / Persian / Arabic / Chinese;
- RTL;
- desktop/mobile;
- keyboard/focus;
- empty/error/unauthorized/reload;
- normal users never see platform administration;
- no dead-end success screens;
- no page requires unexplained internal terminology;
- every main workflow has an obvious next step;
- exact-SHA CI:
  PHPUnit, PHPStan, Pint, Blade, migrations, Vite, npm/composer audit.

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

The next implementation milestone is **C1 — Today, onboarding, and Next Action**.
