# IET / EveryThing

IET is a Laravel-based coordination platform designed around reusable kernels for identity, governed groups, structured interactive Content, semantics, planning, workflows, exchange, commitments, and accounting.

The project deliberately aims for **generic infrastructure internally and focused experiences externally**. A user should interact with concrete products such as a learning group, project workspace, personal planner, hiring flow, or tourism workflow rather than raw generic primitives.

## Current baseline

Active development branch:

`feat/group-spaces-communication`

Validated code baseline before the current architecture-documentation update:

`f57ee430f96afcdb1ecc32f5b644fcb057dae6f4`

Human-owner local validation at that baseline:

- 273 tests passed / 1386 assertions
- PHPStan: no errors
- Pint: passed
- Vite production build: passed

## Canonical project documents

Read these instead of relying on chat history:

1. [Project Compass](docs/PROJECT_COMPASS.md)
2. [Current State](docs/CURRENT_STATE.md)
3. [Target Architecture](docs/TARGET_ARCHITECTURE.md)
4. [Production Roadmap](docs/PRODUCTION_ROADMAP.md)
5. [Phase 1 — Invitation / Registration / Admission](docs/PHASE_01_INVITATION_ONBOARDING.md)
6. [Concept Kernel](docs/CONCEPT_KERNEL.md)
7. [Financial Architecture](docs/FINANCIAL_ARCHITECTURE.md)
8. [Development Circuit](docs/DEVELOPMENT_CIRCUIT.md)
9. [ADR-001 — Identity, authority and simulation boundaries](docs/ADR-001-identity-authority-and-simulation-boundaries.md)

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

The next implementation milestone is **Phase 1: Production Invitation, Registration and Admission Journey**.

Agents must read `AGENTS.md` and the canonical docs before changing code.

## Laravel

This is a Laravel 13 application running on PHP 8.4. Project-specific Laravel/Boost guidance is committed in `AGENTS.md` and `.ai/rules/`.

Do not infer package versions from generic documentation; inspect the repository before making version-dependent changes.
