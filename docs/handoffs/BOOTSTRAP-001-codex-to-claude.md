# BOOTSTRAP-001 — Establish the project compass and development circuit

## Task state

- **Status:** Review
- **Authoring arm:** Codex
- **Next arm:** Claude, independent documentation review
- **Risk:** Low; documentation only

## Objective

Create the initial durable project compass and AI–human handoff system by combining the human-approved long-term north star, the verified repository audit and correction pass, and existing repository history. Future sessions should be able to orient without access to the originating chats.

## Authoritative inputs

1. The human-supplied final product and architecture north star, condensed into `docs/PROJECT_COMPASS.md`.
2. The verified EveryThing audit and its critical correction pass dated 2026-09-09.
3. Repository instructions in `AGENTS.md` and `CLAUDE.md`.
4. Historical milestone evidence in `Development-CodexReports/`.
5. Current repository source and Git history where they establish verified present state.

## Accepted decisions and invariants

- Everything is a modular Laravel monolith.
- Entity is a universal identity and attachment surface, not a universal business model.
- User, Actor, Group, Membership, contextual authorization, Concept, Space, Content, Agreement, Plan, Result, Evidence, Contract, Reputation, and Ledger retain distinct responsibilities.
- Membership is participation truth; contextual RBAC and policies are authorization truth.
- Semantic relations and Concepts never grant access or replace operational records.
- Published or accepted history is immutable and versioned.
- Critical multi-model workflows use explicit transactional actions.
- Accounting remains strongly typed.
- Dynamic builders follow stable domains.
- Unresolved product semantics require human approval before dependent implementation.

## Scope

### Included

- Durable product destination and vocabulary.
- Verified present-state summary and corrected audit blockers.
- Frozen architecture boundaries and unresolved decisions.
- Dependency-driven development stages and acceptance stories.
- Human, ChatGPT, Codex, Claude, Linear, and GitHub responsibilities.
- Workflow states, implementation entry requirements, Git discipline, and handoff format.

### Excluded

- Any application implementation or correction.
- PHP, Blade, JavaScript, CSS, migrations, configuration, tests, dependencies, and generated assets.
- Database reads or writes and migrations.
- Authorization/group-integrity implementation.
- Linear issue, Git branch, commit, pull request, or release creation.

## Files and systems examined

- `AGENTS.md`, `CLAUDE.md`, and the Laravel skeleton `README.md`.
- All Markdown reports under `Development-CodexReports/`.
- Documentation and architecture/workflow filename inventory.
- Current branches and recent commit history.
- Verified audit evidence and correction results for application, schema, tests, dependencies, and Git state.
- Human-supplied north-star and collaboration-circuit specifications.

No secrets, environment values, signed URLs, or email contents were read into or reproduced in these documents.

## Changes made

- Added `docs/PROJECT_COMPASS.md` as the durable destination, boundaries, vocabulary, and verified-state source.
- Added `docs/DEVELOPMENT_CIRCUIT.md` as the collaboration and Git workflow source.
- Added `docs/handoffs/README.md` as the handoff convention.
- Added this living bootstrap handoff.

The existing `Development-CodexReports/` files were preserved as historical milestone evidence. They were not replaced because they contain valuable exact implementation and validation history, while the new documents serve a different durable-governance purpose.

## Commands and validation

- Inspected repository instructions, Markdown inventory, milestone reports, branches, recent Git history, and initial status.
- Confirmed all required Project Compass concepts, architecture sections, acceptance stories, governance, development-circuit actors, workflow states, and prompt fields are present.
- Confirmed all Markdown code fences are balanced across the four files.
- Confirmed no trailing whitespace in the four files.
- Searched `docs/` for local user paths, signed-link parameters, tokens, mail links, and email-address patterns; no matches were found.
- Checked relative Markdown links in `docs/`; all referenced repository paths exist.
- Inspected the final Git status and documentation diff summary.

No application tests, static analysis, formatter, build, migrations, database queries, or dependency commands were run because this task changes documentation only.

## Risks and unresolved questions

- Actor administration authority remains a human product decision.
- The permanent User-to-Actor invariant remains undecided.
- One-versus-many contextual roles and Membership reactivation semantics remain undecided.
- Membership lifecycle states, the first-administrator bootstrap, the initial permission matrix, and production database topology remain undecided.
- Linear project and issue identifiers do not yet exist.

## Repository linkage

- **Branch:** `feat/group-invitation-foundation`
- **Commit(s):** Not created
- **Pull request:** Not created
- **Linear issue:** Not created

Two unrelated pre-existing untracked workspace artifacts were present before this documentation task. They are outside scope and must remain untouched.

## Reviewer findings

Pending independent Claude review.

## Final outcome

The initial repository compass and development circuit are ready for independent review. Only the four requested Markdown files were added; no application behavior or existing file was changed.

## Prompt for next arm

```text
TASK: BOOTSTRAP-001 — Independently review the initial Everything project compass and development circuit.
ROLE OF THIS ARM: Claude, independent architecture and documentation reviewer. Do not implement application behavior.
AUTHORITATIVE INPUTS: docs/PROJECT_COMPASS.md; docs/DEVELOPMENT_CIRCUIT.md; docs/handoffs/README.md; this handoff; AGENTS.md; CLAUDE.md; relevant historical reports in Development-CodexReports/.
CURRENT VERIFIED STATE: Authentication and User/Actor foundations exist; Group/Invitation/RBAC prototypes exist but have verified authorization, lifecycle, concurrency, relation-cache, render-write, coverage, and static-analysis blockers documented in the compass. The current suite passes 71 tests/319 assertions and static analysis has five Group/Invitation errors as of 2026-09-09.
ACCEPTED DECISIONS: Preserve every frozen boundary in the Project Compass. Distinguish destination from current implementation. Historical reports are evidence, not the live roadmap.
OBJECTIVE: Determine whether the four new documents are internally consistent, concise, complete enough for future arms, faithful to the north star and verified audit, and free of claims that speculative capabilities already exist.
INCLUDED: Documentation accuracy; contradictions; missing durable decisions; authority boundaries; workflow clarity; handoff usability; privacy review.
EXCLUDED: PHP, Blade, JavaScript, CSS, migrations, configuration, tests, dependencies, database state, generated assets, and implementation of the next milestone.
ACCEPTANCE CRITERIA: Report every material contradiction or unsupported claim; confirm all required concepts and frozen boundaries are represented; confirm one next milestone is identified without beginning it; confirm the workflow assigns responsibilities and authority unambiguously; confirm no sensitive data is present.
REQUIRED TESTS: Documentation inspection, internal-link/path check, and final Git-status inspection only. Do not claim application tests unless actually run.
RISKS: Accidentally treating the destination as current behavior; silently resolving open product semantics; creating a competing source of truth.
REQUIRED OUTPUT: Update this same handoff with reviewer findings, exact checks, required corrections, and a concise prompt for the human decision hub. Do not create another handoff for this review.
STOP CONDITIONS: Stop and request human resolution if a proposed correction changes a frozen boundary, product semantics, task scope, or repository workflow authority.
```
