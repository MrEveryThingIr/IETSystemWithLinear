# Experience Composition Roadmap

## Authority

This roadmap operationalizes `docs/IET-FULL-SYSTEM-REVIEW-2026-10-01.md`.

The review remains the product diagnosis. This file records implementation status and
acceptance gates so future agents do not reinterpret the UX work as a domain rewrite.

## Non-negotiable invariants

- Existing domain models remain authoritative.
- `FeatureSurfaceRegistry` / `FeatureSurfaceAccess` remain publication authority.
- Record policies remain domain authorization authority.
- Experience grouping must never grant access.
- Existing named routes remain compatible until an explicit consolidation phase.
- User/Actor, Content/Context, Group Agreement/Contract, and the deal lifecycle remain distinct.
- New composition should derive state from existing records rather than create parallel truth.

## Phase 0 — Preserve behavior

Status: **complete for the experience-composition branch**.

Evidence:

- full-system review records the 85 interactive route / 84 experience baseline;
- existing publication, domain-authorization, Today, Content, Group, Planner, Deal, Profile,
  Business, Accounting, and release tests remain intact;
- `ExperienceCompositionNavigationTest` adds explicit boundaries for the new navigation layer.

No model, migration, policy, or route contract changed.

## Phase 1 — Navigation and language

Status: **implemented; owner browser acceptance pending**.

Implemented:

- separate `ExperienceNavigation` projection;
- ordinary work grouped by human goal rather than subsystem inventory;
- Today remains the operating home;
- Needs & Offers;
- Work → Deals / Planner;
- Organizations → Businesses / Groups; Real Estate is a specialized Business vertical, not a peer destination;
- Money → Personal Money / Accounting / Exchange;
- Content;
- Profile and Vault moved to Account & preferences;
- Manual and System Map moved under Help & learning;
- AI Chat moved under Labs;
- platform administration moved into a distinct Administration area;
- English, Persian, Arabic, and Simplified Chinese group labels;
- existing surface grants and deep-link authorization remain unchanged.

Browser acceptance questions:

1. Does the sidebar feel calmer and understandable without knowing internal architecture?
2. Is it obvious where work, organizations, money, content, and needs/offers belong?
3. Are advanced/admin capabilities sufficiently separated from ordinary work?
4. Are expandable groups comfortable on desktop and mobile?
5. Does Persian/Arabic RTL remain visually coherent?
6. Can every previously visible granted facility still be reached?

Do not proceed with route consolidation based only on aesthetics; first accept the
navigation mental model.

### Business correction after browser acceptance

The owner correctly rejected Real Estate as a peer navigation destination. The
canonical Business direction is now tracked in `docs/BUSINESS-PLATFORM-ROADMAP.md`:
Real Estate is the first specialized Business vertical, while Businesses becomes
the reusable operating container for shops, services, offices, workshops, and
future industries. Existing Real Estate URLs remain compatibility/intake channels.

## Phase 2 — Progressive onboarding and Today

Planned after Phase 1 acceptance:

- state-derived onboarding checklist;
- remove persistent verification-success noise;
- derive next-best actions from authoritative records;
- make Today answer what needs attention now and why.

## Phase 3 — Consolidated entry experiences

Planned:

- make Deals the canonical Relationship directory entry while preserving redirect compatibility;
- My / Discover modes for Needs & Offers;
- Money default with Accounting advanced;
- unified Content entry for My Content / Library;
- Help center composition including System Map.

## Phase 4 — Contextual workflow shells

Planned:

- Deal pipeline shell;
- Content studio shell;
- Group shell;
- Organization shell;
- standard page contract: purpose, state, next action, consequence, help, durable result.

## Phase 5 — Acceptance and release hardening

Planned:

- novice / ordinary / manager / operator personas;
- English / Persian / Arabic / Chinese and RTL;
- desktop / mobile / keyboard / focus;
- empty / invalid / unauthorized / reload;
- full PHPUnit / PHPStan / Pint / Blade / migrations / Vite / security / remote CI;
- exact-SHA owner browser acceptance.
