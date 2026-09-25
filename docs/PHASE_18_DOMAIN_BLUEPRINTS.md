# Phase 18 — Journey / Relationship / Domain Blueprints

## Status

Runtime kernel and guided experience implemented on `feat/ideal-v1-18-domain-blueprints`.

Runtime checkpoint before documentation/manual closure:

- SHA: `e4c9cb4cce39a1e0a37bad6ae72e97b6ab5feb77`
- CI: `36119387963`
- Result: **510 tests / 3138 assertions**
- Pint: 638 changed PHP files green
- PHPStan: green
- Vite: green
- migration rollback/reapply: green
- scheduler/database queue/backup smoke: green
- npm audit: 0 vulnerabilities
- Composer audit: no advisories

Local/browser/mobile/RTL acceptance remains deferred to the cumulative release worksheet under the continuous remote execution contract.

## Purpose

Productize combinations that have already been proven by earlier kernels, without introducing duplicate domain engines or allowing configuration to replace authority.

A Domain Blueprint is a versioned composition recipe. It may provide:

- domain-friendly terminology;
- recommended capabilities;
- useful Content Blueprint references;
- guided-entry defaults;
- provenance for the exact recipe version that guided creation.

It is not a Workflow engine, permission bundle, Contract, executable script, or universal entity.

## Implemented model

### DomainBlueprint

Stable identity for a reusable journey recipe.

Important invariants:

- UUID and slug are immutable;
- a Blueprint cannot be deleted because instantiated objects may preserve its provenance;
- the current published version is selected explicitly;
- built-in recipes are idempotently ensured.

### DomainBlueprintVersion

Immutable published configuration containing:

- `journey_kind`;
- `terminology`;
- `capabilities`;
- `content_blueprint_slugs`;
- `guided_entry`;
- deterministic `content_hash`;
- creator/published provenance.

Published versions cannot be edited or deleted. A changed built-in recipe produces a new version.

### Supported journey kinds

Phase 18 deliberately starts with only the kinds proven by existing product surfaces:

- Relationship;
- Personal Activity.

The kind is enforced when applying a Blueprint. A Personal Activity recipe cannot guide Relationship creation and a Relationship recipe cannot be applied as a Personal Activity Plan recipe.

## Initial built-in compositions

The system currently ensures six published recipes:

1. **Simple Sale**
2. **Rental**
3. **Service Job**
4. **Employment / Paid Work**
5. **Construction Partnership**
6. **Personal Activity**

The five collaboration recipes guide Relationship creation. Personal Activity guides Planner creation.

This is intentionally a small curated catalog. Phase 18 does not create a user-programmable workflow language.

## Guided experience

Authenticated product navigation exposes **Journeys**.

Each Journey card shows:

- human purpose/name;
- recipe version;
- recommended capabilities;
- useful Content templates;
- an explicit start action.

### Relationship journey

A selected Relationship Blueprint may prefill:

- creator role;
- participant role;
- purpose search hint.

The user still selects the real Concept, participant, title and other editable details.

The resulting Relationship permanently records the exact `domain_blueprint_version_id`, and the creation event stores the version UUID as provenance.

### Personal Activity journey

The Personal Activity Blueprint may prefill safe Planner defaults such as:

- schedule frequency;
- duration.

The user still provides the actual activity, date/time and other Plan details.

The resulting Plan permanently records the exact DomainBlueprintVersion and creation-event provenance.

## Authority boundary

Blueprint configuration never creates downstream truth.

For example, a Service Job recipe may recommend:

`Conversation → Content → Planner → Proposal → Contract → Commitment → Fulfillment → Financial → Accounting`

but starting that Journey creates only the domain object the user explicitly submits at that step.

It does **not** automatically create:

- Proposal;
- Contract/ContractVersion;
- Commitment;
- Planner Occurrence beyond an explicitly created Plan;
- Fulfillment;
- Financial Obligation;
- Settlement;
- JournalEntry;
- ownership/equity/employment truth.

Every downstream capability remains governed by its own policies and transactional Actions.

## Provenance rule

Existing objects keep the exact recipe version that guided them.

If a Blueprint later publishes version 2:

- a Relationship created from version 1 stays bound to version 1;
- a Plan created from version 1 stays bound to version 1;
- version 1 remains immutable evidence;
- future guided creation may use version 2.

This is the same anti-drift principle used elsewhere for published Content, ProposalVersion, ContractVersion and other versioned evidence.

## Security and configuration rules

Domain Blueprint configuration is data, not executable application code.

Phase 18 configuration is normalized through a trusted registry of supported capabilities. It does not execute arbitrary PHP, Blade, JavaScript, SQL or CSS.

Possessing a Blueprint slug/UUID/version never grants access to another domain object.

Target-domain policies and Actions always reauthorize protected reads and mutations.

## Tests

Focused Phase 18 coverage includes:

- six built-in recipes are published and idempotent;
- configuration hashes are stable;
- published versions are immutable;
- exact Blueprint version survives later Blueprint upgrade;
- wrong journey-kind application is rejected;
- Personal Activity does not create Contract/Commitment/financial/accounting truth;
- Journeys catalog exposes all six compositions;
- Service Job guided Relationship creation persists exact provenance;
- Personal Activity guided Planner creation persists exact provenance;
- existing model-factory contract includes the new models.

## Migration

`database/migrations/2026_09_25_020000_create_domain_blueprints.php`

The migration adds the versioned Blueprint substrate and exact optional Blueprint-version bindings required by the proven Relationship/Plan entry points.

## Documentation-as-Content

System Manual Chapter 23, **Journeys and Domain Blueprints**, teaches:

- what a Journey is;
- exactly where to start it in the UI;
- Service Job and Personal Activity examples;
- exact version provenance;
- authorization boundaries;
- negative guarantees.

## Exit gate

Phase 18 is ready for integration only when:

- runtime CI remains fully green;
- the System Manual materializes 23 chapters idempotently;
- manual topic routing for Journeys is covered;
- this phase contract and closure report are committed;
- `CURRENT_STATE.md`, `PRODUCTION_ROADMAP.md`, and `LOCAL_ACCEPTANCE_WORKSHEET.md` are synchronized;
- final feature-branch CI is green;
- the integration PR is green.

## Next dependency

After Phase 18 integration, Phase 19 may implement **Need / Offer Matching**.

Matching is discovery only. It must be explainable and privacy-safe, and its only authoritative handoff is into the already-proven Relationship/Proposal path. A Match itself creates no obligation.
