# Phase 18 Closure — Journey / Relationship / Domain Blueprints

## Result

Phase 18 implements the first production composition layer over already-proven IET kernels.

- Branch: `feat/ideal-v1-18-domain-blueprints`
- Baseline: `ec284490ac1e43b384abfbe9ee7bba1bf15e84f9` (Phase 17 integration)
- Runtime checkpoint: `e4c9cb4cce39a1e0a37bad6ae72e97b6ab5feb77`
- Runtime CI: `36119387963`
- Runtime result: **510 tests / 3138 assertions**
- Local/browser/mobile/RTL/accessibility acceptance: deferred cumulatively

## Product result

IET now exposes a **Journeys** entry point that lets a person begin from a familiar purpose instead of manually discovering every generic kernel.

The initial catalog contains:

- Simple Sale;
- Rental;
- Service Job;
- Employment / Paid Work;
- Construction Partnership;
- Personal Activity.

These are not new domain silos. They are curated recipes over the existing Relationship, Planner, Content, Proposal, Contract, Commitment, Fulfillment, Financial and Accounting capabilities.

## Versioning result

`DomainBlueprint` provides stable recipe identity.

`DomainBlueprintVersion` provides immutable published configuration with:

- journey kind;
- terminology;
- recommended capabilities;
- Content Blueprint references;
- guided-entry defaults;
- deterministic content hash;
- publication/creator provenance.

Built-in recipes are ensured idempotently. A changed recipe publishes a new version rather than mutating a prior published version.

## Provenance result

Relationship and Plan may record an exact `domain_blueprint_version_id`.

Creation events also preserve the Blueprint-version UUID.

A later recipe update therefore does not rewrite the provenance of an existing Relationship or Plan.

## Guided-experience result

Relationship recipes can guide:

- creator role;
- participant role;
- purpose search hint.

Personal Activity can guide Planner defaults such as:

- frequency;
- duration.

Users still supply/adjust the real business details before submission.

The resulting Relationship/Plan page shows the exact Journey recipe version that guided it.

## Authority result

Blueprints are advisory configuration, not authority.

Starting a Journey does not automatically create Proposal, Contract, Commitment, Fulfillment, Financial Obligation, Settlement, JournalEntry, ownership, employment, or other downstream truth.

Every authoritative mutation remains in the owning domain policy and transactional Action.

A Blueprint slug/UUID/version grants no permissions.

Journey-kind checks prevent applying a Personal Activity recipe to Relationship creation or a Relationship recipe to Personal Activity Plan creation.

## Configuration-safety result

Supported capabilities are normalized through a trusted registry.

Blueprint configuration does not execute arbitrary PHP, Blade, JavaScript, SQL or CSS.

This preserves the project rule that generic configuration may shape trusted application behavior but cannot become an unbounded runtime interpreter.

## Documentation result

Phase 18 adds:

- `docs/PHASE_18_DOMAIN_BLUEPRINTS.md`;
- System Manual Chapter 23 — **Journeys and Domain Blueprints**;
- manual topic routing regression coverage;
- Checkpoint 18 in `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`;
- synchronized `docs/CURRENT_STATE.md`;
- synchronized `docs/PRODUCTION_ROADMAP.md`.

The manual now teaches exact Service Job and Personal Activity UI flows plus negative guarantees.

## Validation

Runtime CI `36119387963` is green:

- 510 tests / 3138 assertions;
- Pint: 638 changed PHP files clean;
- PHPStan clean;
- Vite production build green;
- Phase 16/17/18 migration rollback/reapply smoke green;
- scheduler/database-queue smoke green;
- SQLite backup/restore smoke green;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

The final documentation/manual closure head must receive its own green CI before integration. The integration PR is the final remote Phase 18 gate.

## Known limitations

Phase 18 intentionally does not yet provide:

- user-authored arbitrary Domain Blueprint programming;
- generic Workflow execution;
- automatic chaining of recommended capabilities;
- Need/Offer matching;
- notifications/realtime delivery;
- domain-pack marketplaces;
- production-wide UX/localization polish.

Those remain later roadmap work.

## Next

Phase 19 — **Need / Offer Matching**.

Matching must be explainable and privacy-safe, must reuse existing ActorProfileIntent/Concept constraints, and may explicitly hand off to Relationship/Proposal. A Match itself creates no agreement or obligation.
