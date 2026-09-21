# Phase 2 — Delivery and Operations Baseline

## Status

Active on `feat/phase-02-delivery-operations`, starting from accepted Phase 1 closure commit `a91c0dea1e770614d1d419f26a9bf1783b38e023`.

Provider-neutral operational implementation may proceed. External provider selection, production deployment credentials, and claims of a successful restore drill remain human/infrastructure gates.

## Objective

Establish the operational floor required to deploy, observe, recover, diagnose, and safely evolve IET before adding new domain kernels.

Phase 2 is primarily operational architecture. It must improve delivery/reliability of the existing application without pulling Concept, Profile, Admission v2, real-time collaboration, generic Workflow, Planner, Need/Offer, or Finance forward.

## Starting point

Phase 1 closes with a validated onboarding kernel and local production build:

- 282 tests / 1437 assertions;
- PHPStan clean;
- Pint clean;
- Vite build clean;
- invitation acceptance rate limited;
- manual private-link invitation delivery documented;
- no new external service selected merely to finish Phase 1.

## Deliverables

### CI quality gate

Create CI for pull requests/branches with the repository's authoritative checks, including as applicable:

~~~text
composer install / dependency integrity
php artisan test --compact
vendor/bin/phpstan analyse
Pint verification appropriate for CI
npm ci
npm run build
~~~

CI configuration must match installed Laravel/PHP/Node/package versions rather than assumptions.

### Environment and deployment runbook

Document:

- required PHP/Node/database/runtime versions;
- required environment variables grouped by concern;
- secret handling rules;
- build/deploy order;
- migration policy;
- cache/config/route handling;
- maintenance/rollback procedure;
- release verification.

Do not put real secrets in the repository.

### Queue and scheduler operating model

Define and prove:

- queue connection expectations by environment;
- worker supervision/restart behavior;
- retry/backoff/failure policy;
- failed-job inspection/retry process;
- scheduler invocation/supervision;
- idempotency expectations for queued work.

### Transactional email operations

Phase 1 accepted manual private invitation-link delivery. Phase 2 establishes the production mail operating model without changing onboarding semantics.

Cover:

- environment-specific mail transport;
- queued mail where appropriate;
- verification/password-reset reliability;
- invitation email capability only if product choice enables it;
- localization strategy;
- retry/failure visibility;
- safe URL handling and no sensitive Admission content;
- provider credentials/configuration kept outside source control.

Selecting a provider with meaningful product/cost/compliance implications remains a human decision.

### Error reporting, monitoring and logs

Define the minimum production visibility for:

- unhandled exceptions;
- failed jobs;
- queue health;
- scheduler health;
- application/runtime logs;
- critical onboarding/auth failures;
- deploy/version correlation.

A new third-party monitoring service requires explicit owner approval if it has meaningful product/cost/data implications.

### Database backup and restore

Document and perform a restore drill appropriate to the chosen deployment/database environment:

- backup frequency/retention expectation;
- encryption/access;
- restore procedure;
- migration/version compatibility;
- verification after restore;
- evidence that the drill actually ran.

A backup policy is incomplete until restore is tested.

### Storage/media operations

Document existing private Asset storage requirements:

- production disk/storage choice;
- private access model;
- upload limits;
- processing/scanning expectations;
- retention/backups;
- authorized streaming/download;
- failure behavior.

Do not redesign Assets merely to complete this phase.

### Abuse/rate-limit review

Inventory auth/onboarding sensitive endpoints and confirm appropriate controls for:

- login;
- registration;
- verification resend;
- password reset;
- invitation acceptance;
- other obvious mutation/abuse surfaces present now.

Do not apply one shared throttle bucket indiscriminately to unrelated actions.

### Dependency/security update process

Define repeatable handling for:

- Composer/npm dependency updates;
- vulnerability review;
- framework/package upgrade cadence;
- lockfile integrity;
- regression validation.

## Explicitly excluded

Do not implement in Phase 2:

- Concept Kernel;
- progressive Profile;
- generic Context migration;
- Admission Context/Admission v2;
- generic Conversation abstraction;
- WebSocket/broadcast product features beyond what operations themselves require;
- generic Submission/Workflow;
- Planner;
- Need/Offer/Matching;
- negotiated Contract kernel;
- Accounting/Financial Laboratory;
- speculative migrations for later phases.

The approved future Admission collaboration direction is `docs/ADMISSION_COLLABORATION_ARCHITECTURE.md`; Phase 2 must make the platform operationally ready for it, not implement it early.

## Testing and evidence

Develop with the narrowest useful checks for each operational change. Before closure run all applicable repository validation and record CI/deployment/backup/restore evidence honestly.

Do not claim that a deployment, restore drill, queue worker, scheduler, or external service works unless it was actually exercised in the relevant environment.

## Required report

Write:

`Development-CodexReports/phase-02-delivery-operations-report.md`

Include:

- starting commit/branch;
- environment assumptions;
- CI implementation;
- deployment/runbook changes;
- queue/scheduler decisions;
- mail decisions;
- monitoring/logging decisions;
- backup/restore procedure and actual drill result;
- storage/media guidance;
- abuse/rate-limit review;
- dependency/security process;
- tests/validation;
- checks requiring human/infrastructure action;
- unresolved risks;
- final Git state;
- recommended next gate.

## Stop conditions

Stop for owner/architecture review before:

- choosing/adding a paid or privacy-significant external provider;
- changing User/Actor or Invitation→Admission→Membership semantics;
- weakening evidence/authorization boundaries;
- introducing Phase 3+ domain models;
- destructive migrations/data loss;
- exposing private Assets publicly;
- claiming backup/restore without a real restore test.

## Exit gate

Phase 2 is complete only when a release candidate can be built and its operating model can be followed without developer memory, including:

- CI enforces the required test/static/build gates;
- deployment/environment runbook is complete for the selected target;
- queue/scheduler operations are documented and verified where used;
- transactional email operation is documented/verified for enabled mail paths;
- failed jobs and operational errors are diagnosable;
- backup procedure exists and restore has actually been tested;
- production storage/media requirements are documented;
- sensitive endpoint abuse controls are reviewed;
- dependency/security update process is documented;
- applicable final validation is green;
- implementation report is committed;
- human owner accepts the operational gate.
