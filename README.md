# IET / EveryThing

IET is a Laravel-based coordination platform designed around reusable kernels for identity, governed groups, structured interactive Content, semantics, planning, workflows, exchange, commitments, and accounting.

The project deliberately aims for **generic infrastructure internally and focused experiences externally**. A user should interact with concrete products such as a learning group, project workspace, personal planner, hiring flow, or tourism workflow rather than raw generic primitives.

## Current baseline

Active roadmap phase:

`Phase 4 — Actor/Party and Progressive Profile`

Completed Phase 2 branch:

`feat/phase-02-delivery-operations`

Accepted Phase 1 closure commit:

`a91c0dea1e770614d1d419f26a9bf1783b38e023`

Phase 1 closure validation:

- 282 tests passed / 1437 assertions
- PHPStan: no errors
- Pint: passed
- Vite production build: passed
- browser onboarding/hardening accepted by the human owner

## Canonical project documents

Read these instead of relying on chat history:

1. [Project Compass](docs/PROJECT_COMPASS.md)
2. [Current State](docs/CURRENT_STATE.md)
3. [Target Architecture](docs/TARGET_ARCHITECTURE.md)
4. [Production Roadmap](docs/PRODUCTION_ROADMAP.md)
5. [Phase 2 — Delivery and Operations](docs/PHASE_02_DELIVERY_OPERATIONS.md)
6. [Operations Runbook](docs/OPERATIONS_RUNBOOK.md)
7. [Admission Collaboration Architecture](docs/ADMISSION_COLLABORATION_ARCHITECTURE.md)
8. [Completed Phase 1 — Invitation / Registration / Admission](docs/PHASE_01_INVITATION_ONBOARDING.md)
9. [Concept Kernel](docs/CONCEPT_KERNEL.md)
10. [Completed Phase 3 — Concept Kernel](docs/PHASE_03_CONCEPT_KERNEL.md)
11. [Active Phase 4 — Actor/Party and Progressive Profile](docs/PHASE_04_ACTOR_PROFILE.md)
12. [Financial Architecture](docs/FINANCIAL_ARCHITECTURE.md)
13. [Development Circuit](docs/DEVELOPMENT_CIRCUIT.md)
14. [ADR-001 — Identity, authority and simulation boundaries](docs/ADR-001-identity-authority-and-simulation-boundaries.md)

Historical implementation reports are under `Development-CodexReports/`.

## Current implemented foundation

The repository already contains substantial implementations for:

- User/Actor identity separation;
- authentication, verification and password reset;
- platform access grants;
- Groups, Memberships, contextual roles/permissions;
- invitation-based Admission and Agreement evidence;
- ownership transfer integrity;
- Group Spaces and restricted participation;
- chat and replies;
- structured Content Definitions and immutable versions;
- Content revisions with active/draft pointers;
- blocks and rich media/assets;
- safe presentation templates;
- Book/Lesson/Page Outline composition;
- immutable publication evidence;
- Reader/Studio separation;
- contextual annotations, questions/answers/replies, private notes and reactions.

See `docs/CURRENT_STATE.md` for the precise snapshot and known limitations.

## Development rule

Work on one accepted roadmap phase at a time.

Phases 2 and 3 are complete. **Phase 4 — Actor/Party and Progressive Profile** is active on `feat/phase-04-actor-profile`. Milestone 4A (professional identity + profile media foundation) is remote-CI green and awaits owner-local/browser acceptance before 4B begins. Production-host-specific deployment, mail-provider, supervisor, monitoring, and backup/restore proof remain mandatory before production release and are tracked by the operations runbook and later production-hardening/release phases.

Agents must read `AGENTS.md` and the canonical docs before changing code.

## Laravel

This is a Laravel 13 application running on PHP 8.4. Project-specific Laravel/Boost guidance is committed in `AGENTS.md` and `.ai/rules/`.

Do not infer package versions from generic documentation; inspect the repository before making version-dependent changes.
