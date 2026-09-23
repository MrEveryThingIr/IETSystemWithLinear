# IET / EveryThing

IET is a Laravel-based coordination platform designed around reusable kernels for identity, governed groups, structured interactive Content, semantics, planning, workflows, exchange, commitments, and accounting.

The project deliberately aims for **generic infrastructure internally and focused experiences externally**. A user should interact with concrete products such as a learning group, project workspace, personal planner, hiring flow, or tourism workflow rather than raw generic primitives.

## Current baseline

Current accepted product line:

- Phases 0–6 are complete and accepted.
- Phase 7 — Submission / Response / Evaluation is runtime-complete and automated-green; owner-local/browser/mobile/RTL acceptance remains the gate before Phase 8.
- The Documentation-as-Content audit is implemented on the Phase 7 line.
- The active pre-release branch is `feat/pre-release-office-intent-registry`, productizing a small invitation-only alpha without opening Phase 8.
- The alpha adds standalone Access Invitations, a guided Need/Offer wizard, and a permission-aware read-only Needs/Offers/Services directory over the existing Profile Intent domain.
- AI assistance remains in the codebase but is disabled by default for this alpha; Development Origin provenance remains available to authorized platform auditors.

Read `docs/CURRENT_STATE.md` for exact commits, CI evidence, and the current local acceptance gate.

## Canonical project documents

Read these instead of relying on chat history:

1. [Project Compass](docs/PROJECT_COMPASS.md)
2. [Current State](docs/CURRENT_STATE.md)
3. [Target Architecture](docs/TARGET_ARCHITECTURE.md)
4. [Production Roadmap](docs/PRODUCTION_ROADMAP.md)
5. [Phase 7 — Submission / Response / Evaluation](docs/PHASE_07_SUBMISSION_EVALUATION.md)
6. [AI Assistance and Development Provenance](docs/AI_ASSISTANCE_AND_DEVELOPMENT_PROVENANCE.md)
7. [Development Circuit](docs/DEVELOPMENT_CIRCUIT.md)
8. [Admission Collaboration Architecture](docs/ADMISSION_COLLABORATION_ARCHITECTURE.md)
9. [Operations Runbook](docs/OPERATIONS_RUNBOOK.md)
10. [ADR-001 — Identity, authority and simulation boundaries](docs/ADR-001-identity-authority-and-simulation-boundaries.md)

Completed phase contracts and historical implementation reports remain under `docs/` and `Development-CodexReports/`.

## Current implemented foundation

The repository already contains substantial implementations for:

- User/Actor identity separation;
- authentication, verification and password reset;
- platform access grants;
- Groups, Memberships, contextual roles/permissions;
- standalone system Access Invitations for new accounts;
- invitation-based Group Admission and Agreement evidence;
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
- contextual annotations, questions/answers/replies, private notes and reactions;
- versioned Content Blueprints and unified Context authoring;
- Submission / Response / Evaluation with immutable evidence;
- authenticated Reference Context and the official System Manual as normal Content;
- guided current-intent capture for Needs/Offers across property, goods, services, capital and collaboration;
- permission-aware read-only intent discovery with clear Need/Offer/Service filtering;
- plan-first AI-assisted Content editing through existing policies/Actions, feature-gated off by default for the first alpha;
- immutable Development Origins linking design discussions to phases, versions, commits, and repository paths.

See `docs/CURRENT_STATE.md` for the precise snapshot and known limitations.

## Development rule

Work on one accepted roadmap phase at a time. The active pre-release office-intent slice is explicitly authorized over the Phase 7/manual/AI-provenance baseline; it is not Phase 8 and must not invent automated Matching, Contract, Planner, payment, or Accounting truth.

Do not begin Admission v2, generic Conversation, realtime infrastructure, Workflow, Planner, Contract/Commitment, Matching, or Accounting work until their roadmap gate is opened.

Agents must read `AGENTS.md`, the canonical docs, and matching `.ai/rules/` before changing code. AI assistance never bypasses authorization, executes generated code, or turns natural-language wording into an authoritative domain transition.

## Laravel

This is a Laravel 13 application running on PHP 8.4. Project-specific Laravel/Boost guidance is committed in `AGENTS.md` and `.ai/rules/`.

Do not infer package versions from generic documentation; inspect the repository before making version-dependent changes.
